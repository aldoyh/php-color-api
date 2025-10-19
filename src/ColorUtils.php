<?php declare(strict_types=1);

namespace Frontify\ColorApi;

class ColorUtils {
    /**
     * Map of name => hex (uppercase '#RRGGBB')
     * Loaded from colors.json when available and merged with a small fallback list.
     */
    private array $colorNames = [];

    public function __construct()
    {
        // fallback set
        $this->colorNames = [
            'red' => '#FF0000',
            'green' => '#00FF00',
            'blue' => '#0000FF',
            'white' => '#FFFFFF',
            'black' => '#000000',
            'yellow' => '#FFFF00',
            'cyan' => '#00FFFF',
            'magenta' => '#FF00FF',
            'silver' => '#C0C0C0',
            'gray' => '#808080',
        ];

        // Merge colors.json if present
        $colorsFile = __DIR__ . '/../colors.json';
        if (is_file($colorsFile)) {
            $json = @json_decode((string) file_get_contents($colorsFile), true);
            if (isset($json['colors']) && is_array($json['colors'])) {
                foreach ($json['colors'] as $c) {
                    if (!empty($c['name']) && !empty($c['hex'])) {
                        $this->colorNames[strtolower($c['name'])] = $this->normalizeHex($c['hex']);
                    }
                }
            }
        }
    }

    public function isValidHex($hex): bool
    {
        return preg_match('/^#?([0-9A-F]{3}|[0-9A-F]{6})$/i', (string) $hex) === 1;
    }

    public function normalizeHex($hex): string
    {
        $hex = strtoupper(trim((string) $hex));
        $hex = ltrim($hex, '#');

        if (strlen($hex) === 3) {
            // expand shorthand (e.g. 'abc' => 'AABBCC')
            $hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
        }

        return '#' . $hex;
    }

    public function hexToRgb($hex): array
    {
        $hex = ltrim((string) $hex, '#');
        if (strlen($hex) === 3) {
            $hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
        }

        return [
            'r' => (int) hexdec(substr($hex, 0, 2)),
            'g' => (int) hexdec(substr($hex, 2, 2)),
            'b' => (int) hexdec(substr($hex, 4, 2)),
        ];
    }

    public function rgbToHex($r, $g, $b): string
    {
        return '#' . strtoupper(sprintf('%02X%02X%02X', (int) $r, (int) $g, (int) $b));
    }

    public function rgbToHsl($r, $g, $b): array
    {
        $r /= 255;
        $g /= 255;
        $b /= 255;

        $max = max($r, $g, $b);
        $min = min($r, $g, $b);
        $l = ($max + $min) / 2;
        $d = $max - $min;

        if ($d === 0) {
            $h = 0;
            $s = 0;
        } else {
            $s = $l > 0.5 ? $d / (2 - $max - $min) : $d / ($max + $min);
            switch ($max) {
                case $r:
                    $h = (($g - $b) / $d + ($g < $b ? 6 : 0)) / 6;
                    break;
                case $g:
                    $h = (($b - $r) / $d + 2) / 6;
                    break;
                default:
                    $h = (($r - $g) / $d + 4) / 6;
                    break;
            }
        }

        return ['h' => $h * 360, 's' => $s, 'l' => $l];
    }

    public function hslToRgb($h, $s, $l): array
    {
        $h = fmod($h, 360) / 360;
        $c = (1 - abs(2 * $l - 1)) * $s;
        $x = $c * (1 - abs(fmod($h * 6, 2) - 1));
        $m = $l - $c / 2;

        if ($h < 1/6) { $r = $c; $g = $x; $b = 0; }
        elseif ($h < 2/6) { $r = $x; $g = $c; $b = 0; }
        elseif ($h < 3/6) { $r = 0; $g = $c; $b = $x; }
        elseif ($h < 4/6) { $r = 0; $g = $x; $b = $c; }
        elseif ($h < 5/6) { $r = $x; $g = 0; $b = $c; }
        else { $r = $c; $g = 0; $b = $x; }

        return [
            'r' => (int) round(($r + $m) * 255),
            'g' => (int) round(($g + $m) * 255),
            'b' => (int) round(($b + $m) * 255),
        ];
    }

    public function getColorByName($name): ?string
    {
        $key = strtolower((string) $name);
        return $this->colorNames[$key] ?? null;
    }

    /**
     * Return color info. Preserves previous shape ({hex, rgb, hsl, name}) but uses
     * the nearest known color name when possible.
     */
    public function getColorInfo($hex): array
    {
        $hex = $this->normalizeHex($hex);
        $rgb = $this->hexToRgb($hex);
        $hsl = $this->rgbToHsl($rgb['r'], $rgb['g'], $rgb['b']);

        // find nearest named color
        $nearest = $this->findNearestNamedColor($hex);

        return [
            'hex' => $hex,
            'rgb' => sprintf('%d, %d, %d', $rgb['r'], $rgb['g'], $rgb['b']),
            'hsl' => sprintf('%.0f, %.1f%%, %.1f%%', $hsl['h'], $hsl['s'] * 100, $hsl['l'] * 100),
            'name' => $nearest['name'] ?? 'Custom Color'
        ];
    }

    private function findNearestNamedColor(string $hex): array
    {
        $target = $this->hexToRgb($hex);
        $best = ['name' => null, 'hex' => null, 'distance' => PHP_INT_MAX];

        foreach ($this->colorNames as $name => $namedHex) {
            $c = $this->hexToRgb($namedHex);
            $d = ($c['r'] - $target['r']) ** 2 + ($c['g'] - $target['g']) ** 2 + ($c['b'] - $target['b']) ** 2;
            if ($d < $best['distance']) {
                $best = ['name' => $name, 'hex' => $namedHex, 'distance' => $d];
            }
            if ($d === 0) { // exact match
                break;
            }
        }

        return $best;
    }

    public function generatePalette($baseHex, $mode = 'complementary') {
        $baseHex = $this->normalizeHex($baseHex);
        $baseRgb = $this->hexToRgb($baseHex);
        $baseHsl = $this->rgbToHsl($baseRgb['r'], $baseRgb['g'], $baseRgb['b']);

        $palette = [];

        switch ($mode) {
            case 'analogous':
                $offsets = [0, 30, -30];
                break;
            case 'complementary':
                $offsets = [0, 180];
                break;
            case 'triadic':
                $offsets = [0, 120, 240];
                break;
            case 'tetradic':
                $offsets = [0, 90, 180, 270];
                break;
            case 'shades':
                for ($i = 0; $i <= 5; $i++) {
                    $lightness = max(0, $baseHsl['l'] - ($i * 0.15));
                    $rgb = $this->hslToRgb($baseHsl['h'], $baseHsl['s'], $lightness);
                    $palette[] = $this->getColorInfo($this->rgbToHex($rgb['r'], $rgb['g'], $rgb['b']));
                }
                return $palette;
            case 'tints':
                for ($i = 0; $i <= 5; $i++) {
                    $lightness = min(1, $baseHsl['l'] + ($i * 0.15));
                    $rgb = $this->hslToRgb($baseHsl['h'], $baseHsl['s'], $lightness);
                    $palette[] = $this->getColorInfo($this->rgbToHex($rgb['r'], $rgb['g'], $rgb['b']));
                }
                return $palette;
            default:
                $offsets = [0, 180];
        }

        foreach ($offsets as $offset) {
            $hue = fmod($baseHsl['h'] + $offset + 360, 360);
            $rgb = $this->hslToRgb($hue, $baseHsl['s'], $baseHsl['l']);
            $palette[] = $this->getColorInfo($this->rgbToHex($rgb['r'], $rgb['g'], $rgb['b']));
        }

        return $palette;
    }
}
