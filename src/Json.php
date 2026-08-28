<?php

declare(strict_types=1);

namespace Payzum;

/**
 * JSON decoding that does not silently round money.
 *
 * The payments surface returns monetary fields as JSON numbers, frozen for
 * NowPayments compatibility. PHP's json_decode turns those into float, and
 * JSON_BIGINT_AS_STRING does not help — it only covers large integers. So
 * 0.123456789012345678 comes back as 0.12345678901234568: digits gone, with no
 * error and no warning.
 *
 * decodeLossless() walks the raw JSON and quotes every number literal before
 * handing it to json_decode, so numbers arrive as exact decimal strings. The
 * walk has to skip over string contents properly — a naive regex would also
 * quote digits that appear inside a string value, and would trip over escaped
 * quotes.
 *
 * Worth being honest about the ceiling: the gateway itself casts these values
 * to double before serialising, so the digits are already lost upstream. What
 * this buys is that the SDK adds no *further* loss and hands back exactly what
 * the server sent. For genuinely exact amounts, read the buyer surface
 * (GET /v1/invoices/{id}/status), whose amounts are decimal strings end to end.
 */
final class Json
{
    /**
     * Decode JSON with every number preserved as an exact decimal string.
     *
     * @return array<string, mixed>
     * @throws Errors\PayzumException on malformed JSON
     */
    public static function decodeLossless(string $json): array
    {
        $decoded = json_decode(self::quoteNumbers($json), true);

        if (!is_array($decoded)) {
            throw new Errors\PayzumException(
                'Malformed JSON in API response: ' . json_last_error_msg(),
            );
        }

        return $decoded;
    }

    /** Plain decode, for payloads with no monetary fields. */
    public static function decode(string $json): array
    {
        $decoded = json_decode($json, true);

        if (!is_array($decoded)) {
            throw new Errors\PayzumException(
                'Malformed JSON in API response: ' . json_last_error_msg(),
            );
        }

        return $decoded;
    }

    /**
     * Wrap every JSON number literal in quotes, leaving string contents alone.
     *
     * Single pass, two states: inside a string or outside it. Inside, the only
     * thing that matters is honouring backslash escapes so an escaped quote
     * does not look like the end of the string.
     */
    private static function quoteNumbers(string $json): string
    {
        $out = '';
        $len = strlen($json);
        $inString = false;
        $i = 0;

        while ($i < $len) {
            $c = $json[$i];

            if ($inString) {
                $out .= $c;
                if ($c === '\\' && $i + 1 < $len) {
                    // Copy the escaped character verbatim; it can be a quote.
                    $out .= $json[$i + 1];
                    $i += 2;
                    continue;
                }
                if ($c === '"') {
                    $inString = false;
                }
                $i++;
                continue;
            }

            if ($c === '"') {
                $inString = true;
                $out .= $c;
                $i++;
                continue;
            }

            // A number literal can only start right after : [ , or whitespace,
            // which is exactly where a '-' or digit is legal outside a string.
            if ($c === '-' || ($c >= '0' && $c <= '9')) {
                $start = $i;
                if ($c === '-') {
                    $i++;
                }
                while ($i < $len && $json[$i] >= '0' && $json[$i] <= '9') {
                    $i++;
                }
                if ($i < $len && $json[$i] === '.') {
                    $i++;
                    while ($i < $len && $json[$i] >= '0' && $json[$i] <= '9') {
                        $i++;
                    }
                }
                if ($i < $len && ($json[$i] === 'e' || $json[$i] === 'E')) {
                    $i++;
                    if ($i < $len && ($json[$i] === '+' || $json[$i] === '-')) {
                        $i++;
                    }
                    while ($i < $len && $json[$i] >= '0' && $json[$i] <= '9') {
                        $i++;
                    }
                }
                $out .= '"' . substr($json, $start, $i - $start) . '"';
                continue;
            }

            $out .= $c;
            $i++;
        }

        return $out;
    }
}
