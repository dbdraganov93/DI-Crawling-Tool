<?php
/**
 * Class Marktjagd_Service_Input_UrlReader
 */
class Marktjagd_Service_Input_UrlReader
{
    private const DEFAULT_URL_AGENT = 'User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.124 Safari/537.36';

    public function getContent(string $url, string $urlAgent = self::DEFAULT_URL_AGENT): string
    {
        $options = [
            'http' => [
                'header' => $urlAgent,
            ],
        ];

        $context = stream_context_create($options);
        $content = file_get_contents($url, false, $context);

        return $content;
    }
}
