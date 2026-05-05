<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class DeviceHeartbeatRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    protected function prepareForValidation(): void
    {
        $normalized = [];

        $aliases = [
            'localIp' => 'local_ip',
            'appVersion' => 'app_version',
            'lastHeartbeatAt' => 'last_heartbeat_at',
            'lastSyncAt' => 'last_sync_at',
            'networkStrength' => 'network_strength',
            'batteryPercent' => 'battery_percent',
            'deviceType' => 'device_type',
        ];

        foreach ($aliases as $source => $target) {
            if ($this->has($source) && ! $this->has($target)) {
                $normalized[$target] = $this->input($source);
            }
        }

        if ($this->has('os') && ! $this->has('os_name')) {
            $os = trim((string) $this->input('os'));

            if ($os !== '') {
                $parts = preg_split('/\s+/', $os, 2);
                $normalized['os_name'] = $parts[0] ?? $os;

                if (! $this->has('os_version') && isset($parts[1])) {
                    $normalized['os_version'] = $parts[1];
                }
            }
        }

        if (is_string($this->input('capabilities'))) {
            $normalized['capabilities'] = $this->parseCapabilitiesString(
                (string) $this->input('capabilities')
            );
        }

        if ($this->has('lastResult')) {
            $metrics = $this->input('metrics', []);
            $metrics = is_array($metrics) ? $metrics : [];
            $metrics['last_result'] = $this->input('lastResult');
            $normalized['metrics'] = $metrics;
        }

        if ($normalized !== []) {
            $this->merge($normalized);
        }
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'device_type' => ['nullable', 'string', Rule::in(['android', 'minipc_kiosk', 'print_agent'])],
            'local_ip' => ['nullable', 'ip'],
            'battery_percent' => ['nullable', 'integer', 'min:0', 'max:100'],
            'network_strength' => ['nullable', 'integer', 'min:0', 'max:100'],
            'app_version' => ['nullable', 'string', 'max:30'],
            'os_name' => ['nullable', 'string', 'max:50'],
            'os_version' => ['nullable', 'string', 'max:30'],
            'capabilities' => ['nullable', 'array'],
            'capabilities.*' => ['boolean'],
            'metrics' => ['nullable', 'array'],
            'last_heartbeat_at' => ['nullable', 'date'],
            'last_sync_at' => ['nullable', 'date'],
        ];
    }

    /**
     * @return array<string, bool>
     */
    private function parseCapabilitiesString(string $capabilities): array
    {
        return collect(explode(',', $capabilities))
            ->mapWithKeys(function (string $entry): array {
                [$key, $value] = array_pad(explode('=', trim($entry), 2), 2, 'false');

                $normalizedKey = trim($key);

                if ($normalizedKey === '') {
                    return [];
                }

                return [
                    $normalizedKey => filter_var(
                        trim($value),
                        FILTER_VALIDATE_BOOLEAN,
                        FILTER_NULL_ON_FAILURE
                    ) ?? false,
                ];
            })
            ->all();
    }
}
