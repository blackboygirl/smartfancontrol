<?php
/** Smart Fan Control AJAX API for Unraid webGui. */
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

const SFC_VERSION = '0.1.4';
const SFC_CONFIG = '/boot/config/plugins/smartfancontrol/config.json';
const SFC_RUN = '/run/smartfancontrol';
const SFC_STATUS = SFC_RUN . '/status.json';
const SFC_PID = SFC_RUN . '/smartfancontrol.pid';
const SFC_RC = '/etc/rc.d/rc.smartfancontrol';

function respond(array $data, int $status = 200): never {
    http_response_code($status);
    echo json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    exit;
}

function readJson(string $path, array $fallback = []): array {
    $raw = @file_get_contents($path);
    if ($raw === false) return $fallback;
    $d = json_decode($raw, true);
    return is_array($d) ? $d : $fallback;
}

function writeJsonAtomic(string $file, array $data): bool {
    @mkdir(dirname($file), 0755, true);
    $tmp = $file . '.tmp.' . getmypid();
    $json = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    if ($json === false || @file_put_contents($tmp, $json . "\n", LOCK_EX) === false) return false;
    @chmod($tmp, 0644);
    return @rename($tmp, $file);
}

function defaultConfig(): array {
    return ['version' => 1, 'enabled' => false, 'interval' => 5, 'profiles' => []];
}

function devicePath(string $hw): string {
    $p = @realpath($hw . '/device');
    if ($p === false) $p = @realpath($hw);
    return $p === false ? '' : $p;
}

function scanHardware(): array {
    $pwms = [];
    $fans = [];
    $temps = [];
    foreach (glob('/sys/class/hwmon/hwmon*') ?: [] as $hw) {
        $chip = trim((string)@file_get_contents($hw . '/name'));
        if ($chip === '') continue;
        $device = devicePath($hw);

        foreach (glob($hw . '/pwm[0-9]*') ?: [] as $path) {
            $base = basename($path);
            if (!preg_match('/^pwm[0-9]+$/', $base)) continue;
            $raw = trim((string)@file_get_contents($path));
            $pwms[] = [
                'id' => 'hwmon|' . $chip . '|' . $device . '|' . $base,
                'chip' => $chip, 'device' => $device, 'channel' => $base,
                'label' => $chip . ' - ' . $base,
                'raw' => is_numeric($raw) ? (int)$raw : null,
            ];
        }

        foreach (glob($hw . '/fan*_input') ?: [] as $path) {
            $base = basename($path);
            $raw = trim((string)@file_get_contents($path));
            $labelFile = preg_replace('/_input$/', '_label', $path);
            $label = is_file($labelFile) ? trim((string)@file_get_contents($labelFile)) : $base;
            $fans[] = [
                'id' => 'hwmon|' . $chip . '|' . $device . '|' . $base,
                'chip' => $chip, 'device' => $device, 'channel' => $base,
                'label' => $chip . ' - ' . $label,
                'rpm' => is_numeric($raw) ? (int)$raw : null,
            ];
        }

        foreach (glob($hw . '/temp*_input') ?: [] as $path) {
            $base = basename($path);
            $raw = trim((string)@file_get_contents($path));
            if (!is_numeric($raw)) continue;
            $v = (float)$raw;
            if (abs($v) > 500) $v /= 1000.0;
            if ($v < -50 || $v > 200) continue; // Filter obvious bogus NCT values.
            $stem = preg_replace('/_input$/', '', $path);
            $label = is_file($stem . '_label') ? trim((string)@file_get_contents($stem . '_label')) : basename($stem);
            $temps[] = [
                'id' => 'hwtemp|' . $chip . '|' . $device . '|' . $base,
                'type' => 'hwmon', 'chip' => $chip, 'device' => $device, 'input' => $base,
                'label' => $chip . ' - ' . $label,
                'temp' => round($v, 1),
            ];
        }
    }

    $gpus = [];
    $nvidia = '/usr/bin/nvidia-smi';
    if (is_executable($nvidia)) {
        $lines = []; $rc = 0;
        @exec(escapeshellarg($nvidia) . ' --query-gpu=index,uuid,name,temperature.gpu --format=csv,noheader,nounits 2>/dev/null', $lines, $rc);
        if ($rc === 0) {
            foreach ($lines as $line) {
                $p = array_map('trim', str_getcsv($line));
                if (count($p) < 4 || !is_numeric($p[3])) continue;
                $gpus[] = [
                    'id' => 'nvidia|' . $p[1],
                    'type' => 'nvidia', 'index' => (int)$p[0], 'uuid' => $p[1], 'name' => $p[2],
                    'label' => 'NVIDIA ' . $p[2] . ' (GPU ' . $p[0] . ')', 'temp' => (float)$p[3],
                ];
            }
        }
    }

    $arrayMax = null;
    $rawDisks = @file_get_contents('/var/local/emhttp/disks.ini');
    if ($rawDisks !== false && preg_match_all('/^temp="?([0-9]{1,3})"?/m', $rawDisks, $m)) {
        $vals = array_filter(array_map('intval', $m[1]), fn($x) => $x > 0 && $x < 100);
        if ($vals) $arrayMax = max($vals);
    }
    if ($arrayMax !== null) {
        $temps[] = [
            'id' => 'array|max', 'type' => 'array_max', 'label' => 'Array disks - highest temperature', 'temp' => (float)$arrayMax,
        ];
    }

    usort($pwms, fn($a,$b) => strcmp($a['label'],$b['label']));
    usort($fans, fn($a,$b) => strcmp($a['label'],$b['label']));
    usort($temps, fn($a,$b) => strcmp($a['label'],$b['label']));
    return ['pwms' => $pwms, 'fans' => $fans, 'temps' => $temps, 'gpus' => $gpus];
}

function clampFloat(mixed $v, float $min, float $max, float $default): float {
    if (!is_numeric($v)) return $default;
    return max($min, min($max, (float)$v));
}

function sanitizeSelector(mixed $v, string $kind): ?array {
    if (!is_array($v)) return null;
    $chip = trim((string)($v['chip'] ?? ''));
    $channel = basename(trim((string)($v['channel'] ?? '')));
    $device = trim((string)($v['device'] ?? ''));
    $rx = $kind === 'pwm' ? '/^pwm[0-9]+$/' : '/^fan[0-9]+_input$/';
    if ($chip === '' || !preg_match($rx, $channel)) return null;
    return ['chip' => $chip, 'device' => $device, 'channel' => $channel];
}

function sanitizeSensor(mixed $s): ?array {
    if (!is_array($s)) return null;
    $type = (string)($s['type'] ?? '');
    $label = trim((string)($s['label'] ?? 'sensor'));
    if ($type === 'nvidia') {
        $uuid = trim((string)($s['uuid'] ?? ''));
        if ($uuid === '') return null;
        return ['type' => 'nvidia', 'uuid' => $uuid, 'label' => $label];
    }
    if ($type === 'array_max') return ['type' => 'array_max', 'label' => $label ?: 'Array max'];
    if ($type === 'hwmon') {
        $chip = trim((string)($s['chip'] ?? ''));
        $device = trim((string)($s['device'] ?? ''));
        $input = basename(trim((string)($s['input'] ?? '')));
        if ($chip === '' || !preg_match('/^temp[0-9]+_input$/', $input)) return null;
        return ['type' => 'hwmon', 'chip' => $chip, 'device' => $device, 'input' => $input, 'label' => $label];
    }
    return null;
}

function sanitizeConfig(array $input): array {
    $out = defaultConfig();
    $out['enabled'] = !empty($input['enabled']);
    $out['interval'] = max(2, min(60, (int)($input['interval'] ?? 5)));
    $profiles = [];
    foreach (($input['profiles'] ?? []) as $idx => $p) {
        if (!is_array($p)) continue;
        $pwm = sanitizeSelector($p['pwm'] ?? null, 'pwm');
        if ($pwm === null) continue;
        $rpm = sanitizeSelector($p['rpm'] ?? null, 'fan');
        $sensors = [];
        foreach (($p['sensors'] ?? []) as $s) {
            $clean = sanitizeSensor($s);
            if ($clean) $sensors[] = $clean;
        }
        if (!$sensors) continue;
        $curve = [];
        foreach (($p['curve'] ?? []) as $pt) {
            if (!is_array($pt) || !is_numeric($pt['temp'] ?? null) || !is_numeric($pt['pwm'] ?? null)) continue;
            $curve[] = ['temp' => clampFloat($pt['temp'], -20, 150, 40), 'pwm' => clampFloat($pt['pwm'], 0, 100, 50)];
        }
        usort($curve, fn($a,$b) => $a['temp'] <=> $b['temp']);
        if (count($curve) < 2) $curve = [['temp'=>40,'pwm'=>40],['temp'=>75,'pwm'=>100]];
        $id = preg_replace('/[^a-zA-Z0-9_-]/', '', (string)($p['id'] ?? '')) ?: ('fan_' . ($idx + 1));
        $profiles[] = [
            'id' => substr($id, 0, 48),
            'name' => substr(trim((string)($p['name'] ?? 'Fan ' . ($idx + 1))), 0, 80),
            'enabled' => !empty($p['enabled']),
            'pwm' => $pwm,
            'rpm' => $rpm,
            'sensor_mode' => (($p['sensor_mode'] ?? 'max') === 'avg') ? 'avg' : 'max',
            'sensors' => $sensors,
            'curve' => $curve,
            'critical_temp' => clampFloat($p['critical_temp'] ?? 80, 20, 150, 80),
            'failsafe_pwm' => clampFloat($p['failsafe_pwm'] ?? 100, 20, 100, 100),
            'min_pwm' => clampFloat($p['min_pwm'] ?? 30, 0, 100, 30),
            'min_rpm' => max(0, min(30000, (int)($p['min_rpm'] ?? 0))),
            'rpm_guard_pwm' => clampFloat($p['rpm_guard_pwm'] ?? 35, 0, 100, 35),
        ];
    }
    $out['profiles'] = $profiles;
    return $out;
}

function daemonRunning(): bool {
    if (!is_file(SFC_PID)) return false;
    $pid = (int)trim((string)@file_get_contents(SFC_PID));
    return $pid > 1 && is_dir('/proc/' . $pid);
}

function runRc(string $action): array {
    if (!in_array($action, ['start','stop','restart','status'], true)) return ['rc'=>2,'output'=>'invalid action'];
    $out = []; $rc = 0;
    @exec(escapeshellarg(SFC_RC) . ' ' . escapeshellarg($action) . ' 2>&1', $out, $rc);
    return ['rc' => $rc, 'output' => implode("\n", $out)];
}

$action = (string)($_POST['action'] ?? $_GET['action'] ?? 'status');

switch ($action) {
    case 'scan':
        respond(['ok' => true, 'hardware' => scanHardware(), 'config' => readJson(SFC_CONFIG, defaultConfig())]);
    case 'status':
        respond(['ok' => true, 'running' => daemonRunning(), 'status' => readJson(SFC_STATUS, []), 'config' => readJson(SFC_CONFIG, defaultConfig())]);
    case 'save':
        $payload = json_decode((string)($_POST['payload'] ?? ''), true);
        if (!is_array($payload)) respond(['ok' => false, 'error' => 'Invalid JSON payload'], 400);
        $config = sanitizeConfig($payload);
        if (!writeJsonAtomic(SFC_CONFIG, $config)) respond(['ok' => false, 'error' => 'Unable to write configuration'], 500);
        $result = runRc($config['enabled'] ? 'restart' : 'stop');
        respond(['ok' => $result['rc'] === 0 || !$config['enabled'], 'config' => $config, 'service' => $result]);
    case 'start':
    case 'stop':
    case 'restart':
        $result = runRc($action);
        respond(['ok' => $result['rc'] === 0, 'service' => $result, 'running' => daemonRunning()]);
    default:
        respond(['ok' => false, 'error' => 'Unknown action'], 400);
}

