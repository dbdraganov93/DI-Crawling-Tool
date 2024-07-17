<?php

require APPLICATION_PATH . '/../vendor/autoload.php';

class Marktjagd_Service_Input_GoogleDriveRead
{
    public function readDrive($url)
    {
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
        $result = curl_exec($ch);
        curl_close($ch);

        $pattern = '#window\[\'_DRIVE_ivd\'\]\s*=\s*\'([^\']+?)\'#';
        if (preg_match($pattern, $result, $match)) {
            $pattern = '#(\\\x[\d|a-f]{2})#';
            if (preg_match_all($pattern, $match[1], $matches)) {
                $exchange = [];
                foreach ($matches[1] as $singleMatch) {
                    $exchange['#\\' . $singleMatch . '#'] = chr(hexdec(substr($singleMatch, 2)));
                }
                $match[1] = preg_replace(array_keys($exchange), $exchange, $match[1]);
            }
        }

        $pattern = '#\["([^"]+?)",\["[^"]+?"\],"([^\"]+?)"#';
        if (preg_match_all($pattern, $match[1], $fileMatches)) {
            $files = array_combine($fileMatches[1], $fileMatches[2]);
        }

        return $files;
    }

    public function downloadFile($idFile, $filePath)
    {
        $url = 'https://drive.google.com/uc?id=' . $idFile . '&authuser=0&export=download';
        exec("curl -L " . escapeshellarg($url) . " --output " . escapeshellarg($filePath), $retValue, $retCode);

        if ($retCode != 0) {
            throw new Exception('unable to download file from' . $url);
        }

        return $filePath;
    }
}