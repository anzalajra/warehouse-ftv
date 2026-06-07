<?php

namespace App\Services;

use BaconQrCode\Common\ErrorCorrectionLevel;
use BaconQrCode\Encoder\Encoder;
use Picqer\Barcode\BarcodeGeneratorPNG;
use RuntimeException;

/**
 * Renders a printable label PNG for a unit/kit serial: a QR or Code128 barcode
 * encoding the closed-system payload (PREFIX:serial) with the human-readable
 * serial stamped beneath it. Deterministic — the same serial yields identical
 * bytes on every reprint.
 *
 * Uses pure GD (QR matrix drawn by hand, barcode via picqer's GD renderer) so
 * it does not require the imagick extension.
 */
class LabelImageService
{
    public function __construct(protected UnitCodeService $codes) {}

    /**
     * @param  'qr'|'barcode'  $type
     * @return string  Raw PNG bytes
     */
    public function png(string $serial, string $type): string
    {
        if (! \function_exists('imagecreatetruecolor')) {
            throw new RuntimeException('The GD PHP extension is required to generate label images.');
        }

        $payload = $this->codes->encode($serial);

        $code = $type === 'barcode'
            ? $this->renderBarcode($payload)
            : $this->renderQr($payload);

        return $this->stampSerial($code, $serial);
    }

    /** Draw the QR matrix onto a GD image. */
    private function renderQr(string $payload)
    {
        $qr = Encoder::encode($payload, ErrorCorrectionLevel::M());
        $matrix = $qr->getMatrix();
        $count = $matrix->getWidth();

        $module = 8;
        $margin = 4 * $module;          // quiet zone
        $size = $count * $module + $margin * 2;

        $img = imagecreatetruecolor($size, $size);
        $white = imagecolorallocate($img, 255, 255, 255);
        $black = imagecolorallocate($img, 0, 0, 0);
        imagefilledrectangle($img, 0, 0, $size, $size, $white);

        for ($y = 0; $y < $count; $y++) {
            for ($x = 0; $x < $count; $x++) {
                if ($matrix->get($x, $y)) {
                    $px = $margin + $x * $module;
                    $py = $margin + $y * $module;
                    imagefilledrectangle($img, $px, $py, $px + $module - 1, $py + $module - 1, $black);
                }
            }
        }

        return $img;
    }

    /** Render a Code128 barcode onto a GD image. */
    private function renderBarcode(string $payload)
    {
        $generator = new BarcodeGeneratorPNG();
        $png = $generator->getBarcode($payload, $generator::TYPE_CODE_128, 2, 90);

        $img = imagecreatefromstring($png);
        if ($img === false) {
            throw new RuntimeException('Failed to render barcode image.');
        }

        return $img;
    }

    /** Compose the code image onto a white label with the serial centered beneath it. */
    private function stampSerial($code, string $serial): string
    {
        $codeW = imagesx($code);
        $codeH = imagesy($code);

        $pad = 18;
        $gap = 12;
        $font = 5;                                  // GD built-in, ~9x15px
        $scale = 2;                                 // upscale the text for legibility
        $charW = imagefontwidth($font);
        $charH = imagefontheight($font);
        $textW = strlen($serial) * $charW * $scale;
        $textH = $charH * $scale;

        $labelW = max($codeW, $textW) + $pad * 2;
        $labelH = $pad + $codeH + $gap + $textH + $pad;

        $label = imagecreatetruecolor($labelW, $labelH);
        $white = imagecolorallocate($label, 255, 255, 255);
        $black = imagecolorallocate($label, 0, 0, 0);
        imagefilledrectangle($label, 0, 0, $labelW, $labelH, $white);

        // Code centered horizontally.
        imagecopy($label, $code, intdiv($labelW - $codeW, 2), $pad, 0, 0, $codeW, $codeH);
        imagedestroy($code);

        // Serial text: render at 1x then scale up so it reads cleanly when printed.
        $rawW = strlen($serial) * $charW;
        $rawH = $charH;
        $textImg = imagecreatetruecolor(max(1, $rawW), max(1, $rawH));
        $tWhite = imagecolorallocate($textImg, 255, 255, 255);
        $tBlack = imagecolorallocate($textImg, 0, 0, 0);
        imagefilledrectangle($textImg, 0, 0, $rawW, $rawH, $tWhite);
        imagestring($textImg, $font, 0, 0, $serial, $tBlack);

        $destX = intdiv($labelW - $textW, 2);
        $destY = $pad + $codeH + $gap;
        imagecopyresized($label, $textImg, $destX, $destY, 0, 0, $textW, $textH, $rawW, $rawH);
        imagedestroy($textImg);

        ob_start();
        imagepng($label);
        $bytes = (string) ob_get_clean();
        imagedestroy($label);

        // Silence unused color allocations under static analysis.
        unset($black);

        return $bytes;
    }
}
