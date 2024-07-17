<?php
/**
 * Service zum Auslesen von PDFs
 */
class Marktjagd_Service_Input_Pdf
{
    /**
     * Gibt den Textinhalt einer PDF-Datei aus
     *
     * @param string $filename Pfad zur lokalen PDF-Datei
     * @return array Textinhalt pro Seite
     */
    public function getText($filename){
        $info = $this->getInfo($filename);
        $lastPage = $info['Pages'];

        $pdfText = array();

        for ($pageIdx=1; $pageIdx<=$lastPage; $pageIdx++){
            $cmd = 'pdftotext -f ' . $pageIdx . ' -l ' . $pageIdx . ' -q ' . $filename . ' /dev/stdout';

            $text = null;
            $result = null;
            exec($cmd, $text, $result);
            if ($result === 1) {
                $logger = Zend_Registry::get('logger');
                $logger->log('failed to execute command \'' . $cmd . '\' with result ' . $result, Zend_Log::ERR);
                continue;
            }
            $text = implode("\n", $text);
            $pdfText[$pageIdx] = $text;
        }

        return $pdfText;
    }

    /**
     * Gibt die Metainformationen einer PDF-Datei aus
     *
     * @param string $filename Pfad zur lokalen PDF-Datei
     * @return array Metainformationen als key value
     */
    public function getInfo($filename){
        $logger = Zend_Registry::get('logger');

        $cmd = 'pdfinfo ' . $filename;
        $return = null;
        exec($cmd, $return, $code);

        if ($code === 1){
            $logger->log('Error while get pdf-info from: ' . $filename, Zend_Log::ERR);
            return false;
        }

        $result = array();
        foreach ($return as $line) {
            $pieces = explode(':', $line);
            $result[trim($pieces[0])] = trim($pieces[1]);
        }
        return $result;
    }
}
