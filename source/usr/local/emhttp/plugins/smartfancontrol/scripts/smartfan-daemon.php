#!/usr/bin/php
<?php
/**
 * Smart Fan Control daemon for Unraid.
 *
 * Controls multiple hwmon PWM channels from one or more temperature sensors.
 * Designed to survive hwmonX renumbering by resolving chip name + device path
 * on every scan instead of persisting /sys/class/hwmon/hwmonX paths.
 */

declare(strict_types=1);

const SFC_VERSION = '0.1.6';

function envp(string $name, string $default): string {
    $v = getenv($name);
    return ($v === false || $v === '') ? $default : $v;
}

function paths(): array {
    $run = envp('SFC_RUN_DIR', '/run/smartfancontrol');
    return [
        'config' => envp('SFC_CONFIG', '/boot/config/plugins/smartfancontrol/config.json'),
        'run' => $run,
        'pid' => $run . '/smartfancontrol.pid',
        'status' => $run . '/status.json',
        'restore' => $run . '/restore.json',
        'log' => envp('SFC_LOG', '/var/log/smartfancontrol.log'),
        'hwmon' => envp('SFC_HWMON_ROOT', '/sys/class/hwmon'),
        'disks_ini' => envp('SFC_DISKS_INI', '/var/local/emhttp/disks.ini'),
        'nvidia_smi' => envp('SFC_NVIDIA_SMI', '/usr/bin/nvidia-smi'),
    ];
}

function ensureDir(string $dir): void {
    if (!is_dir($dir)) @mkdir($dir, 0755, true);
}

function logmsg(string $level, string $message): void {
    $p = paths();
    $line = sprintf("%s [%s] %s\n", date('Y-m-d H:i:s'), strtoupper($level), $message);
    @file_put_contents($p['log'], $line, FILE_APPEND | LOCK_EX);
}

function readJsonFile(string $file, array $fallback = []): array {
    $raw = @file_get_contents($file);
    if ($raw === false || trim($raw) === '') return $fallback;
    $data = json_decode($raw, true);
    return is_array($data) ? $data : $fallback;
}

function writeJsonAtomic(string $file, array $data): bool {
    ensureDir(dirname($file));
    $tmp = $file . '.tmp.' . getmypid();
    $json = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    if ($json === false) return false;
    if (@file_put_contents($tmp, $json . "\n", LOCK_EX) === false) return false;
    @chmod($tmp, 0644);
    return @rename($tmp, $file);
}

function readTrim(string $file): ?string {
    $v = @file_get_contents($file);
    if ($v === false) return null;
    return trim($v);
}

function realDeviceForHwmon(string $hwdir): string {
    $device = @realpath($hwdir . '/device');
    if ($device === false) {
        $device = @realpath($hwdir);
    }
    return $device === false ? '' : $device;
}

function scanHwmon(): array {
    $p = paths();
    $out = [];
    foreach (glob(rtrim($p['hwmon'], '/') . '/hwmon*') ?: [] as $hwdir) {
        $name = readTrim($hwdir . '/name');
        if ($name === null || $name === '') continue;
        $out[] = [
            'path' => $hwdir,
            'chip' => $name,
            'device' => realDeviceForHwmon($hwdir),
        ];
    }
    return $out;
}

function selectorDeviceMatch(array $selector, string $device): bool {
    $wanted = (string)($selector['device'] ?? '');
    if ($wanted === '') return true;
    if ($device === $wanted) return true;
    // Allow persistent suffix matching across /sys path changes.
    return str_ends_with($device, $wanted) || str_ends_with($wanted, basename($device));
}

function resolveHwmonSelector(array $selector): ?string {
    $chip = (string)($selector['chip'] ?? '');
    if ($chip === '') return null;
    foreach (scanHwmon() as $h) {
        if ($h['chip'] !== $chip) continue;
        if (!selectorDeviceMatch($selector, $h['device'])) continue;
        return $h['path'];
    }
    return null;
}

function resolveChannel(array $selector, string $prefix): ?string {
    $hw = resolveHwmonSelector($selector);
    if ($hw === null) return null;
    $channel = basename((string)($selector['channel'] ?? ''));
    if ($channel === '' || !preg_match('/^' . preg_quote($prefix, '/') . '[0-9]+(?:_input)?$/', $channel)) return null;
    $path = $hw . '/' . $channel;
    return is_file($path) ? $path : null;
}

function queryNvidia(): array {
    static $cacheTime = 0.0;
    static $cache = [];
    $now = microtime(true);
    if (($now - $cacheTime) < 1.0) return $cache;
    $cacheTime = $now;
    $cache = [];
    $p = paths();
    $bin = $p['nvidia_smi'];
    if (!is_executable($bin)) return [];
    $cmd = escapeshellarg($bin) . ' --query-gpu=index,uuid,name,temperature.gpu --format=csv,noheader,nounits 2>/dev/null';
    $lines = [];
    $rc = 0;
    @exec($cmd, $lines, $rc);
    if ($rc !== 0) return [];
    foreach ($lines as $line) {
        $parts = array_map('trim', str_getcsv($line));
        if (count($parts) < 4) continue;
        [$index, $uuid, $name, $temp] = $parts;
        if (!is_numeric($temp)) continue;
        $cache[$uuid] = [
            'index' => (int)$index,
            'uuid' => $uuid,
            'name' => $name,
            'temp' => (float)$temp,
        ];
    }
    return $cache;
}

function readArrayMaxTemp(): ?float {
    $p = paths();
    $raw = @file_get_contents($p['disks_ini']);
    if ($raw === false) return null;
    if (!preg_match_all('/^temp="?([0-9]{1,3})"?/m', $raw, $m)) return null;
    $temps = [];
    foreach ($m[1] as $v) {
        $t = (int)$v;
        if ($t > 0 && $t < 100) $temps[] = $t;
    }
    return $temps ? (float)max($temps) : null;
}

function readTemperatureSensor(array $sensor): ?float {
    $type = (string)($sensor['type'] ?? '');
    if ($type === 'nvidia') {
        $uuid = (string)($sensor['uuid'] ?? '');
        $gpus = queryNvidia();
        return isset($gpus[$uuid]) ? (float)$gpus[$uuid]['temp'] : null;
    }
    if ($type === 'array_max') {
        return readArrayMaxTemp();
    }
    if ($type === 'hwmon') {
        $hw = resolveHwmonSelector($sensor);
        if ($hw === null) return null;
        $input = basename((string)($sensor['input'] ?? ''));
        if (!preg_match('/^temp[0-9]+_input$/', $input)) return null;
        $raw = readTrim($hw . '/' . $input);
        if ($raw === null || !is_numeric($raw)) return null;
        $v = (float)$raw;
        // Linux hwmon temperatures are normally millidegrees Celsius.
        if (abs($v) > 500) $v /= 1000.0;
        if ($v < -50 || $v > 200) return null;
        return $v;
    }
    return null;
}

function aggregateSensors(array $sensors, string $mode): array {
    $values = [];
    $details = [];
    foreach ($sensors as $sensor) {
        if (!is_array($sensor)) continue;
        $t = readTemperatureSensor($sensor);
        $details[] = [
            'label' => (string)($sensor['label'] ?? $sensor['name'] ?? $sensor['type'] ?? 'sensor'),
            'temp' => $t,
            'ok' => $t !== null,
        ];
        if ($t !== null) $values[] = $t;
    }
    if (!$values) return ['temp' => null, 'details' => $details];
    $temp = ($mode === 'avg') ? array_sum($values) / count($values) : max($values);
    return ['temp' => round($temp, 1), 'details' => $details];
}

function normalizeCurve(array $curve): array {
    $out = [];
    foreach ($curve as $pt) {
        if (!is_array($pt) || !isset($pt['temp'], $pt['pwm'])) continue;
        if (!is_numeric($pt['temp']) || !is_numeric($pt['pwm'])) continue;
        $out[] = [
            'temp' => max(-20.0, min(150.0, (float)$pt['temp'])),
            'pwm' => max(0.0, min(100.0, (float)$pt['pwm'])),
        ];
    }
    usort($out, fn($a, $b) => $a['temp'] <=> $b['temp']);
    // De-duplicate temperatures; last point wins.
    $dedup = [];
    foreach ($out as $pt) $dedup[(string)$pt['temp']] = $pt;
    $out = array_values($dedup);
    usort($out, fn($a, $b) => $a['temp'] <=> $b['temp']);
    return $out;
}

function curvePwm(float $temp, array $curve): float {
    $curve = normalizeCurve($curve);
    if (!$curve) return 100.0;
    if (count($curve) === 1) return (float)$curve[0]['pwm'];
    if ($temp <= $curve[0]['temp']) return (float)$curve[0]['pwm'];
    $last = $curve[count($curve) - 1];
    if ($temp >= $last['temp']) return (float)$last['pwm'];
    for ($i = 1; $i < count($curve); $i++) {
        $b = $curve[$i];
        $a = $curve[$i - 1];
        if ($temp <= $b['temp']) {
            $span = $b['temp'] - $a['temp'];
            if ($span <= 0) return (float)$b['pwm'];
            $ratio = ($temp - $a['temp']) / $span;
            return $a['pwm'] + (($b['pwm'] - $a['pwm']) * $ratio);
        }
    }
    return 100.0;
}

function pctToRaw(float $pct): int {
    $pct = max(0.0, min(100.0, $pct));
    return (int)round($pct * 255.0 / 100.0);
}

function rawToPct(int $raw): float {
    return round(max(0, min(255, $raw)) * 100.0 / 255.0, 1);
}

function readRpm(?array $selector): ?int {
    if (!$selector) return null;
    $path = resolveChannel($selector, 'fan');
    if ($path === null) return null;
    $v = readTrim($path);
    return ($v !== null && is_numeric($v)) ? max(0, (int)$v) : null;
}

function loadRestore(): array {
    return readJsonFile(paths()['restore'], ['channels' => []]);
}

function saveRestore(array $restore): void {
    writeJsonAtomic(paths()['restore'], $restore);
}

function selectorKey(array $selector): string {
    return implode('|', [
        (string)($selector['chip'] ?? ''),
        (string)($selector['device'] ?? ''),
        (string)($selector['channel'] ?? ''),
    ]);
}

function ensureManualMode(array $selector): array {
    $pwmPath = resolveChannel($selector, 'pwm');
    if ($pwmPath === null) return ['ok' => false, 'error' => 'PWM channel not found'];
    $channel = basename($pwmPath);
    if (!preg_match('/^pwm([0-9]+)$/', $channel, $m)) return ['ok' => false, 'error' => 'Invalid PWM channel'];
    $enablePath = dirname($pwmPath) . '/pwm' . $m[1] . '_enable';
    $restore = loadRestore();
    $restore['channels'] ??= [];
    $key = selectorKey($selector);
    if (!isset($restore['channels'][$key])) {
        $orig = is_file($enablePath) ? readTrim($enablePath) : null;
        $restore['channels'][$key] = [
            'selector' => $selector,
            'enable_original' => ($orig !== null && is_numeric($orig)) ? (int)$orig : null,
            'enable_path_hint' => $enablePath,
        ];
        saveRestore($restore);
    }
    if (is_file($enablePath) && is_writable($enablePath)) {
        // Nuvoton and most hwmon PWM drivers use 1 for manual mode.
        if (@file_put_contents($enablePath, "1\n") === false) {
            return ['ok' => false, 'error' => 'Unable to set manual PWM mode'];
        }
    }
    return ['ok' => true, 'pwm_path' => $pwmPath, 'enable_path' => $enablePath];
}

function setPwm(array $selector, float $pct): array {
    $manual = ensureManualMode($selector);
    if (!$manual['ok']) return $manual;
    $raw = pctToRaw($pct);
    $pwmPath = $manual['pwm_path'];
    if (!is_writable($pwmPath)) return ['ok' => false, 'error' => 'PWM channel is not writable'];
    if (@file_put_contents($pwmPath, (string)$raw . "\n") === false) {
        return ['ok' => false, 'error' => 'PWM write failed'];
    }
    return ['ok' => true, 'raw' => $raw, 'pct' => rawToPct($raw)];
}

function restoreControllers(): int {
    $p = paths();
    $restore = loadRestore();
    $count = 0;
    foreach (($restore['channels'] ?? []) as $entry) {
        if (!is_array($entry) || !isset($entry['selector'])) continue;
        $sel = $entry['selector'];
        $pwmPath = resolveChannel($sel, 'pwm');
        if ($pwmPath === null) continue;
        if (!preg_match('/^pwm([0-9]+)$/', basename($pwmPath), $m)) continue;
        $enablePath = dirname($pwmPath) . '/pwm' . $m[1] . '_enable';
        $orig = $entry['enable_original'] ?? null;
        if ($orig !== null && is_file($enablePath) && is_writable($enablePath)) {
            if (@file_put_contents($enablePath, (string)((int)$orig) . "\n") !== false) $count++;
        }
    }
    @unlink($p['restore']);
    logmsg('info', "Restored {$count} PWM controller enable modes");
    return $count;
}

function validateProfile(array $profile): bool {
    return !empty($profile['enabled'])
        && is_array($profile['pwm'] ?? null)
        && !empty($profile['sensors'])
        && is_array($profile['curve'] ?? null);
}

function controlCycle(array $config, array &$runtime): array {
    $status = [
        'version' => SFC_VERSION,
        'running' => true,
        'timestamp' => time(),
        'profiles' => [],
    ];
    $activeKeys = [];
    foreach (($config['profiles'] ?? []) as $profile) {
        if (!is_array($profile)) continue;
        $id = (string)($profile['id'] ?? uniqid('fan_', false));
        $name = (string)($profile['name'] ?? $id);
        $entry = ['name' => $name, 'enabled' => !empty($profile['enabled'])];
        if (!validateProfile($profile)) {
            $entry['state'] = 'disabled';
            $status['profiles'][$id] = $entry;
            continue;
        }

        $pwmSelector = $profile['pwm'];
        $activeKeys[selectorKey($pwmSelector)] = true;
        $mode = (($profile['sensor_mode'] ?? 'max') === 'avg') ? 'avg' : 'max';
        $agg = aggregateSensors((array)$profile['sensors'], $mode);
        $temp = $agg['temp'];
        $failsafe = max(0.0, min(100.0, (float)($profile['failsafe_pwm'] ?? 100)));
        $critical = (float)($profile['critical_temp'] ?? 80);
        $reason = 'curve';

        if ($temp === null) {
            $target = $failsafe;
            $reason = 'sensor-failsafe';
        } elseif ($temp >= $critical) {
            $target = 100.0;
            $reason = 'critical-temperature';
        } else {
            $target = curvePwm((float)$temp, (array)$profile['curve']);
        }

        $minPwm = max(0.0, min(100.0, (float)($profile['min_pwm'] ?? 0)));
        if ($target > 0 && $target < $minPwm) $target = $minPwm;

        $rpm = readRpm(is_array($profile['rpm'] ?? null) ? $profile['rpm'] : null);
        $minRpm = max(0, (int)($profile['min_rpm'] ?? 0));
        $guardPwm = max(0.0, min(100.0, (float)($profile['rpm_guard_pwm'] ?? 35)));
        $stallKey = $id . ':stall';
        if ($minRpm > 0 && $target >= $guardPwm && $rpm !== null && $rpm < $minRpm) {
            $runtime[$stallKey] = ($runtime[$stallKey] ?? 0) + 1;
        } else {
            $runtime[$stallKey] = 0;
        }
        if (($runtime[$stallKey] ?? 0) >= 3) {
            $target = 100.0;
            $reason = 'rpm-failsafe';
        }

        $write = setPwm($pwmSelector, $target);
        $entry += [
            'state' => $write['ok'] ? 'ok' : 'error',
            'temperature' => $temp,
            'sensors' => $agg['details'],
            'target_pwm' => round($target, 1),
            'applied_pwm' => $write['ok'] ? $write['pct'] : null,
            'rpm' => $rpm,
            'reason' => $reason,
            'error' => $write['ok'] ? null : ($write['error'] ?? 'unknown PWM error'),
        ];
        $status['profiles'][$id] = $entry;
    }

    // Restore any controller that was previously controlled but is no longer active.
    $restore = loadRestore();
    $changed = false;
    foreach (($restore['channels'] ?? []) as $key => $entry) {
        if (isset($activeKeys[$key])) continue;
        if (!is_array($entry) || !isset($entry['selector'])) continue;
        $sel = $entry['selector'];
        $pwmPath = resolveChannel($sel, 'pwm');
        if ($pwmPath !== null && preg_match('/^pwm([0-9]+)$/', basename($pwmPath), $m)) {
            $enablePath = dirname($pwmPath) . '/pwm' . $m[1] . '_enable';
            $orig = $entry['enable_original'] ?? null;
            if ($orig !== null && is_file($enablePath) && is_writable($enablePath)) {
                @file_put_contents($enablePath, (string)((int)$orig) . "\n");
            }
        }
        unset($restore['channels'][$key]);
        $changed = true;
    }
    if ($changed) saveRestore($restore);

    return $status;
}

function defaultConfig(): array {
    return [
        'version' => 1,
        'enabled' => false,
        'interval' => 5,
        'profiles' => [],
    ];
}

function runDaemon(bool $once = false): int {
    $p = paths();
    ensureDir($p['run']);
    $runtime = [];
    @file_put_contents($p['pid'], (string)getmypid() . "\n");
    logmsg('info', 'Smart Fan Control daemon started');
    $lastConfigHash = '';
    do {
        $config = readJsonFile($p['config'], defaultConfig());
        if (empty($config['enabled'])) {
            restoreControllers();
            writeJsonAtomic($p['status'], [
                'version' => SFC_VERSION,
                'running' => true,
                'timestamp' => time(),
                'profiles' => [],
                'message' => 'Global control is disabled',
            ]);
        } else {
            $hash = md5(json_encode($config));
            if ($hash !== $lastConfigHash) {
                logmsg('info', 'Configuration loaded/changed');
                $lastConfigHash = $hash;
            }
            $status = controlCycle($config, $runtime);
            writeJsonAtomic($p['status'], $status);
        }
        if ($once) break;
        $interval = max(2, min(60, (int)($config['interval'] ?? 5)));
        sleep($interval);
    } while (true);
    @unlink($p['pid']);
    if ($once) restoreControllers();
    return 0;
}

function usage(): void {
    fwrite(STDOUT, "Smart Fan Control " . SFC_VERSION . "\n");
    fwrite(STDOUT, "Usage: smartfan-daemon.php [--daemon|--once|--restore|--scan]\n");
}

$arg = $argv[1] ?? '--daemon';
if ($arg === '--restore') {
    exit(restoreControllers() >= 0 ? 0 : 1);
}
if ($arg === '--once') {
    exit(runDaemon(true));
}
if ($arg === '--scan') {
    echo json_encode(scanHwmon(), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
    exit(0);
}
if ($arg === '--daemon') {
    exit(runDaemon(false));
}
usage();
exit(2);

