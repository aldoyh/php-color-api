<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use Frontify\ColorApi\ColorUtils;

final class ColorUtilsTest extends TestCase
{
    public function testNormalizeHexShort(): void
    {
        $u = new ColorUtils();
        $this->assertEquals('#AABBCC', $u->normalizeHex('abc'));
        $this->assertEquals('#ABCDEF', $u->normalizeHex('#abcdef'));
    }

    public function testIsValidHex(): void
    {
        $u = new ColorUtils();
        $this->assertTrue($u->isValidHex('#abcdef'));
        $this->assertTrue($u->isValidHex('abc'));
        $this->assertFalse($u->isValidHex('zzz'));
    }

    public function testHexToRgbAndBack(): void
    {
        $u = new ColorUtils();
        $rgb = $u->hexToRgb('#00ff00');
        $this->assertEquals(0, $rgb['r']);
        $this->assertEquals(255, $rgb['g']);
        $this->assertEquals(0, $rgb['b']);
        $this->assertEquals('#00FF00', $u->rgbToHex($rgb['r'], $rgb['g'], $rgb['b']));
    }

    public function testRgbToHslAndBack(): void
    {
        $u = new ColorUtils();
        $hsl = $u->rgbToHsl(255, 0, 0);
        $this->assertIsArray($hsl);
        $rgb = $u->hslToRgb($hsl['h'], $hsl['s'], $hsl['l']);
        $this->assertEquals(255, $rgb['r']);
        $this->assertEquals(0, $rgb['g']);
        $this->assertEquals(0, $rgb['b']);
    }

    public function testGeneratePaletteCounts(): void
    {
        $u = new ColorUtils();
        $p = $u->generatePalette('#2196f3', 'complementary');
        $this->assertIsArray($p);
        $this->assertCount(2, $p);

        $p2 = $u->generatePalette('#2196f3', 'analogous');
        $this->assertIsArray($p2);
        $this->assertGreaterThanOrEqual(2, count($p2));
    }
}
