<?php

/**
 * @param string $text
 * @throws RuntimeException
 * @return string
 */
$text = 'Weiße Ware';
$text = trim($text);
$text = convertToAscii($text);
$text = strtolower($text);
$text = convertSpecialCharsToDashes($text);

echo $text . "\n";

/**
 * @param string $text
 * @throws RuntimeException
 * @return string
 */
function convertToAscii($text) {
    $locale = 'de_DE.UTF-8';

    if (false === setlocale(LC_CTYPE, $locale)) {
        throw new RuntimeException(
        sprintf('Unable to set locale to "%s"', $locale)
        );
    }

    return iconv('UTF-8', 'ASCII//TRANSLIT', $text);
}

/**
 * @param string $text
 * @return string
 */
function convertSpecialCharsToDashes($text) {
    // use "-" for spaces and union characters
    return preg_replace('/[^0-9a-zA-Z.!]+/', '-', $text);
}
