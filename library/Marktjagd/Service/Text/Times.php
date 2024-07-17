<?php

/**
 * Beinhaltet Funktionen zum Umwandeln/Formatieren von Zeiten
 */
class Marktjagd_Service_Text_Times
{

    public $_aMonthName = array(
        '01' => '#Jan#i',
        '02' => '#Feb#i',
        '03' => '#M[^b]{1,3}r|Mar#i',
        '04' => '#Apr#i',
        '05' => '#Mai|May#i',
        '06' => '#Jun#i',
        '07' => '#Jul#i',
        '08' => '#Aug#i',
        '09' => '#Sep#i',
        '10' => '#Okt|Oct#i',
        '11' => '#Nov#i',
        '12' => '#Dez|Dec#i'
    );

    /**
     * Versucht einen MJ-Uhrzeitenstring aus dem übergebenen String zu extrahieren
     *
     * @param $text
     * @param string $type mögliche Typen (text, table)
     * @param bool $splitByDay wochentagübergreifende Konstrukte zerlegen
     * @return string
     */
    public function generateMjOpenings($text, $type = 'text', $splitByDay = false, $localCode = '')
    {
        if (strlen($localCode)) {
            $text = $this->_localizeTime($text, $localCode);
        }

        $text = $this->_cleanOpenings($text, $type);

        $text = $this->convertAmPmTo24Hours($text);
        $aPattern = $this->_generatePregMatchPattern();

        $aOpening = $this->getWeekdays('keys');

        $sOpenings = '';

        // Generierung Pattern für ersten preg_match
        $pattern = '';
        if ($type == 'text') {
            $pattern = $aPattern['patternText'];
        }

        if ($type == 'table') {
            $pattern = $aPattern['patternTable'];
        }

        // Prüfen, ob überhaupt etwas matcht
        if (preg_match_all($pattern, $text, $matchesOpenings)) {
            foreach ($matchesOpenings[0] as $timesString) {
                $aWeekday = array();
                // einzelne Wochentage (Mo) bzw. Wochentag-Ranges (Mo-Fr) matchen
                preg_match($aPattern['patternDay'], $timesString, $matchDays);
                $aDaySplit = preg_split($aPattern['patternWeekDayAdd'], trim($matchDays[0]));

                // Prüfen welche Wochentage mit Uhrzeiten gesetzt werden müssen
                foreach ($aDaySplit as $weekdaySplit) {
                    $aWeekday = $this->_checkForNecessaryWeekdays($aWeekday, $weekdaySplit);
                }

                // Für jeden gesetzten Tag die Uhrzeit(en) setzen
                foreach ($aWeekday as $weekday) {
                    $aTimes = $this->_addTimes($timesString);
                    $aOpening = $this->_checkExistingWeekday($aOpening, $aTimes, $weekday);
                }
            }
        }

        $aOpening = $this->_splitTimesByDay($aOpening);

        // Generierung des Uhrzeitenstrings
        foreach ($aOpening as $weekday => $aTimes) {
            if (is_array($aTimes)) {
                foreach ($aTimes as $time) {
                    if (strlen($sOpenings)) {
                        $sOpenings .= ', ';
                    }

                    $sOpenings .= $weekday . ' ' . $time;
                }
            }
        }

        return $sOpenings;
    }

    /**
     *
     * @param string $strTime
     * @param string $localCode
     *
     * @return string
     */
    protected function _localizeTime($strTime, $localCode)
    {
        $localCode = strtoupper(substr($localCode, 0, 2));
        $aFrenchDaysPattern = [
            '#dim.*?\s*([^A-Za-z])#i',
            '#lu.*?\s*([^A-Za-z])#i',
            '#ma.*?\s*([^A-Za-z])#i',
            '#me.*?\s*([^A-Za-z])#i',
            '#je.*?\s*([^A-Za-z])#i',
            '#ve.*?\s*([^A-Za-z])#i',
            '#sa.*?\s*([^A-Za-z])#i',
            '#\s*(au|à)\s*#i',
            '#\s*du\s*#i',
            '#\s*de\s*#i',
            '#(\d)h(\d)#i',
            '#(\d)h\s*([^\d])#i',
            '#le\s+#i',
        ];

        $aEnglishDaysPattern = [
            '#su.*?\s*([^A-Za-z])#i',
            '#mo.*?\s*([^A-Za-z])#i',
            '#tu.*?\s*([^A-Za-z])#i',
            '#we.*?\s*([^A-Za-z])#i',
            '#th.*?\s*([^A-Za-z])#i',
            '#fr.*?\s*([^A-Za-z])#i',
            '#sa.*?\s*([^A-Za-z])#i',
            '#(\d)h(\d)#i',
            '#(\d)h-#i',
        ];

        $aItalianDaysPattern = [
            '#dom.*?\s*([^A-Za-z])#i',
            '#lu.*?\s*([^A-Za-z])#i',
            '#ma.*?\s*([^A-Za-z])#i',
            '#me.*?\s*([^A-Za-z])#i',
            '#gi.*?\s*([^A-Za-z])#i',
            '#ve.*?\s*([^A-Za-z])#i',
            '#sa.*?\s*([^A-Za-z])#i',
        ];

        $aSlDaysPattern = [
            '#djel.*?\s*([^A-Za-z])#i',
            '#tora.*?\s*([^A-Za-z])#i',
            '#rije.*?\s*([^A-Za-z])#i',
            '#tvtr.*?\s*([^A-Za-z])#i',
            '#etak.*?\s*([^A-Za-z])#i',
            '#ubot.*?\s*([^A-Za-z])#i',
            '#djel.*?\s*([^A-Za-z])#i',
        ];

        $aSkDaysPattern = [
            '#delo.*?\s*([^A-Za-z])#i',
            '#toro.*?\s*([^A-Za-z])#i',
            '#reda.*?\s*([^A-Za-z])#i',
            '#vrto.*?\s*([^A-Za-z])#i',
            '#atok.*?\s*([^A-Za-z])#i',
            '#obot.*?\s*([^A-Za-z])#i',
            '#edeľ.*?\s*([^A-Za-z])#i',
        ];

        $aGermanDayReplacement = [
            'So $1',
            'Mo $1',
            'Di $1',
            'Mi $1',
            'Do $1',
            'Fr $1',
            'Sa $1',
            ' - ',
            ' ',
            ' ',
            '$1:$2',
            '$1:00$2',
            '',
        ];

        $aTimeLanguages = [
            'EN' => $aEnglishDaysPattern,
            'DE' => $aEnglishDaysPattern,
            'FR' => $aFrenchDaysPattern,
            'IT' => $aItalianDaysPattern,
        ];

        if (empty($aTimeLanguages[$localCode]) && $strTime) {
            return $this->getOpenHourWithoutDays($strTime, $aGermanDayReplacement);
        }

        return preg_replace($aTimeLanguages[$localCode], $aGermanDayReplacement, $strTime);
    }

    /**
     * Bereinigt den Öffnungszeitenstring
     *
     * @param string $text
     * @param string $type
     * @return string
     */
    protected function _cleanOpenings($text, $type)
    {
        $aPattern = array(
            '#\s*\bwochentag(s)*\s*#i',
            '#\s*\bso[a-z]*\-?\s*(und)?\s*feier[a-z]*\s*#i',
            '#\s*\bsu[a-z]*\-?\s*(and)?\s*holi[a-z]*\s*#i',
            '#\s*\bmo[a-z]*\s*#i',
            '#\s*\bmo\s*\.*\s*#i',
            '#\s*\bdi[a-z]*\s*#i',
            '#\s*\bdi\s*\.*\s*#i',
            '#\s*\btu[a-z]*\s*#i',
            '#\s*\bmi[a-z]*\s*#i',
            '#\s*\bmi\s*\.*\s*#i',
            '#\s*\bwe[a-z]*\s*#i',
            '#\s*\bdo[a-z]*\s*#i',
            '#\s*\bdo\s*\.*\s*#i',
            '#\s*\bth[a-z]*\s*#i',
            '#\s*\bfr[a-z]*\s*#i',
            '#\s*\bfr\s*\.*\s*#i',
            '#\s*\bsa[a-z]*\s*#i',
            '#\s*\bsa\s*\.*\s*#i',
            '#\s*\bso[a-z]*\s*#i',
            '#\s*\bso\s*\.*\s*#i',
            '#\s*\bsu[a-z]*\s*#i',
            '#\s*uhr*\s*#i',
            '#\&ndash;#',
            '#\bbis#',
            '#&nbsp;#',
            '#([0-9])\.#',
            '#\bvon#',
            '# – #',
            '#\s+:\s+#',
            '#\s*à\s*#'
        );

        $aReplacement = array(
            'Mo-Fr ',
            'So ',
            'So ',
            'Mo ',
            'Mo ',
            'Di ',
            'Di ',
            'Di ',
            'Mi ',
            'Mi ',
            'Mi ',
            'Do ',
            'Do ',
            'Do ',
            'Fr ',
            'Fr ',
            'Sa ',
            'Sa ',
            'So ',
            'So ',
            'So ',
            '',
            '-',
            '-',
            ' ',
            '$1:',
            '',
            '-',
            ' ',
            '-'
        );

        $text = preg_replace($aPattern, $aReplacement, $text);

        if ($type == 'text') {
            $text = strip_tags($text);
        }

        return $text;
    }

    /**
     * Konvertiert alle Uhrzeiten eines Strings (10:00 PM) in das 24 Stunden Uhrzeit Format (22:00)
     *
     * @param $sHours
     * @return string
     */
    public function convertAmPmTo24Hours($sHours)
    {
        $retHours = preg_replace_callback(
            '#([0-9]{1,2}(\:[0-9]{1,2})?\s*(am|pm))#is',
            function ($match) {
                return date('H:i', strtotime($match[1]));
            },
            $sHours);

        return $retHours;
    }

    protected function _generatePregMatchPattern()
    {
        $aAdd = array(
            '\/',
            '\,',
            '\&',
            '\+',
            'u\.+',
            'und',
            '\&amp\;'
        );

        $aRange = array(
            '\-',
            '\–',
            'bis',
        );

        $aAddRange = array_merge($aAdd, $aRange);

        $patternDay = '\s*(' . implode('|', $this->getWeekdays()) . ')\s*[.|:]{0,2}\s*'
            . '('
            . '(' . implode('|', $aAddRange) . ')+\s*'
            . '(' . implode('|', $this->getWeekdays()) . ')+\s*[.|:]{0,2}\s*'
            . ')*';

        $patternTime = '\s*([0-9]{1,2})([\:|\.]*([0-9]{2}))*\s*(' . implode('|', $aRange) . ')\s*'
            . '([0-9]{1,2})([\:|\.]*([0-9]{2}))*\s*';

        $patternTimes = $patternTime
            . '((' . implode('|', $aAdd) . ')*\s*'
            . $patternTime . ')*';

        $patternWeekDayAdd = '#\s*(' . implode('|', $aAdd) . ')\s*#is';
        $patternWeekDayRange = '#(' . implode('|', $aRange) . ')#is';

        $patternText = '#' . $patternDay . $patternTimes . '#is';
        $patternTable = '#' . '<tr[^>]*>\s*<td[^>]*>' . $patternDay . '</td>\s*'
            . '<td[^>]*>' . $patternTimes . '</td>\s*</tr>#is';

        return [
            'patternDay' => '#' . $patternDay . '#is',
            'patternTime' => '#' . $patternTime . '#is',
            'patternWeekDayAdd' => $patternWeekDayAdd,
            'patternWeekDayRange' => $patternWeekDayRange,
            'patternText' => $patternText,
            'patternTable' => $patternTable
        ];
    }

    /**
     * Liefert die Wochentage entweder als ArrayValues oder als ArrayKeys zurück
     *
     * @param string $type
     * @return array
     */
    public function getWeekdays($type = 'values')
    {
        if ($type == 'keys') {
            $weekdays = array(
                'Mo' => false,
                'Di' => false,
                'Mi' => false,
                'Do' => false,
                'Fr' => false,
                'Sa' => false,
                'So' => false
            );
        } elseif ($type == 'nextday') {
            $weekdays = array(
                'Mo' => 'Di',
                'Di' => 'Mi',
                'Mi' => 'Do',
                'Do' => 'Fr',
                'Fr' => 'Sa',
                'Sa' => 'So',
                'So' => 'Mo'
            );
        } elseif ($type == 'numbers') {
            $weekdays = array(
                'Mo' => 1,
                'Di' => 2,
                'Mi' => 3,
                'Do' => 4,
                'Fr' => 5,
                'Sa' => 6,
                'So' => 7
            );
        } else {
            $weekdays = array(
                'Mo',
                'Di',
                'Mi',
                'Do',
                'Fr',
                'Sa',
                'So'
            );
        }

        return $weekdays;
    }

    /**
     * @param array $aWeekday Array mit zu Wochentagen, welche eine Öffnungszeit bekommen
     * @param $weekdaySplit String mit Wochentag bzw. Wochentagsrange
     * @return array
     */
    protected function _checkForNecessaryWeekdays($aWeekday, $weekdaySplit)
    {
        $aPattern = $this->_generatePregMatchPattern();

        $weekdaySplit = preg_replace('#(\s*|\.|\:)#', '', $weekdaySplit);
        $weekDaysRange = preg_split($aPattern['patternWeekDayRange'], $weekdaySplit);

        // Prüfen auf Wochentag-Range
        if (count($weekDaysRange) == 2) {
            $inbound = false;

            // Prüfung auf wochenübergreifende Range (Bsp.: So - Di)
            $aWeekdayWithNum = $this->getWeekdays('numbers');
            $aNumWithWeekDay = array_flip($aWeekdayWithNum);
            if ($aWeekdayWithNum[$weekDaysRange[0]] > $aWeekdayWithNum[$weekDaysRange[1]]) {
                for ($i = $aWeekdayWithNum[$weekDaysRange[0]]; $i <= 7; $i++) {
                    $aWeekday[] = $aNumWithWeekDay[$i];
                }

                for ($i = 1; $i <= $aWeekdayWithNum[$weekDaysRange[1]]; $i++) {
                    $aWeekday[] = $aNumWithWeekDay[$i];
                }
            } else {
                foreach ($this->getWeekdays('keys') as $keyWeekDay => $sOpen) {
                    if ($keyWeekDay == $weekDaysRange[0]) {
                        $inbound = true;
                    }

                    // Jeden Tag in der Range setzen
                    if ($inbound) {
                        $aWeekday[] = $keyWeekDay;
                    }

                    if ($keyWeekDay == $weekDaysRange[1]) {
                        break;
                    }
                }
            }
        } else {
            // wenn einzelner Tag, dann nur diesen setzen
            $aWeekday[] = $weekDaysRange[0];
        }

        return $aWeekday;
    }

    /**
     * Generiert die Uhrzeiten für den Öffnungstag bzw. eine Wochentag-Range und liefert diese als Array zurück
     *
     * @param string $timesString
     * @return array
     */
    protected function _addTimes($timesString)
    {
        $aPattern = $this->_generatePregMatchPattern();

        $aTimes = array();
        preg_match_all($aPattern['patternTime'], $timesString, $matchesTimes);

        // alle gematchten Zeiten ermitteln und durchlaufen und ermittelte Strings in ein Uhrzeiten-Array packen
        foreach ($matchesTimes[0] as $keyTimes => $matchesTime) {
            $sTimes = ' ';

            // Prüfen, ob Startzeit 24:00 ist und umwandeln in 00:00
            $matchesTimes[1][$keyTimes] = preg_replace('#24#', '00', $matchesTimes[1][$keyTimes]);

            // Von-Stunden setzen
            $sTimes .= $matchesTimes[1][$keyTimes] . ':';

            // prüfen, ob Von-Minuten gesetzt sind, wenn nicht auf "00" setzen
            if (trim($matchesTimes[3][$keyTimes]) != '') {
                $sTimes .= $matchesTimes[3][$keyTimes];
            } else {
                $sTimes .= '00';
            }

            // Prüfen, ob Endzeit 00:00 ist und umwandeln in 24:00
            if (trim($matchesTimes[7][$keyTimes]) == '00' || trim($matchesTimes[7][$keyTimes]) == '') {
                if (strlen($matchesTimes[5][$keyTimes]) == 2) {
                    $matchesTimes[5][$keyTimes] = preg_replace('#[0]{2}#', '24', $matchesTimes[5][$keyTimes]);
                } else {
                    $matchesTimes[5][$keyTimes] = preg_replace('#[0]{1}#', '24', $matchesTimes[5][$keyTimes]);
                }
            }

            // Bis-Stunden setzen
            $sTimes .= '-' . $matchesTimes[5][$keyTimes] . ':';

            // prüfen, ob Bis-Minuten gesetzt sind, wenn nicht auf "00" setzen
            if (trim($matchesTimes[7][$keyTimes]) != '') {
                $sTimes .= $matchesTimes[7][$keyTimes];
            } else {
                $sTimes .= '00';
            }

            $aTimes[] = trim($sTimes);
        }

        return $aTimes;
    }

    /**
     * Checkt ob schon Uhrzeiten für Wochentag gesetzt wurden und merged bzw. ersetzt ggfs. die Öffnungszeiten
     *
     * @param array $aOpening
     * @param array $aTimes
     * @param string $weekday
     * @return array
     */
    protected function _checkExistingWeekday($aOpening, $aTimes, $weekday)
    {
        // Prüfen, ob schon eine Uhrzeit für den Wochentag gesetzt wurde
        if ($aOpening[$weekday]) {
            // Prüfung, ob Uhrzeit überschrieben oder an bisheriger Uhrzeit angehangen werden soll
            $aExistingOpening = explode('-', $aOpening[$weekday][0]);
            $aNewOpening = explode('-', $aTimes[0]);
            $timesExisting = $aExistingOpening[1];
            $timesNew = $aNewOpening[0];
            /* Prüfung, ob Endzeit der bisher eingetragene Zeit <= Anfangszeit neue Zeit
              => wenn ja, merge
              => wenn nein, ersetzen */
            if (strtotime($timesExisting) <= strtotime($timesNew)) {
                $aOpening[$weekday] = array_merge($aOpening[$weekday], $aTimes);
            } else {
                $aOpening[$weekday] = $aTimes;
            }
        } else {
            $aOpening[$weekday] = $aTimes;
        }

        return $aOpening;
    }

    /**
     * Teilt die Öffnungszeiten auf einzelne Tage, falls diese über 24 Uhr hinausgehen
     *
     * @param $aHoursArray
     * @return array
     */
    protected function _splitTimesByDay($aHoursArray)
    {
        $nextWeekdays = $this->getWeekdays('nextday');
        $aHoursArraySplitted = [];

        foreach ($aHoursArray as $day => $opening) {
            if (is_array($opening)) {
                foreach ($opening as $time) {
                    $match = null;
                    if (preg_match('#([0-9]{1,2}):*([0-9]{2})-([0-9]{1,2}):*([0-9]{2})#', $time, $match)) {
                        if ((int)($match[1] . $match[2]) > (int)($match[3] . $match[4]) && (int)($match[3] . $match[4]) != 0
                        ) {
                            //Öffnungszeit auf teilen (heute bis 24:00, morgen ab 00:00 Uhr)
                            $aHoursArraySplitted[$day][] = $match[1] . ':' . $match[2] . '-24:00';
                            $aHoursArraySplitted[$nextWeekdays[$day]][] = '00:00-' . $match[3] . ':' . $match[4];
                        } else {
                            $aHoursArraySplitted[$day][] = $time;
                        }
                    }
                }
            }
        }

        return $aHoursArraySplitted;
    }

    /**
     * Gibt das Ende einer Woche zurück.
     *
     * @param int $year
     * @param int $week
     * @param int $weeklen
     * @return int timestamp
     */
    public function getEndOfWeek($year, $week, $weeklen = 6)
    {
        return $this->getBeginOfWeek($year, $week) + $weeklen * 86400 - 1;
    }

    /**
     * Gibt den Beginn einer Woche zurück.
     *
     * @param int $year
     * @param int $week
     * @return int timestamp
     */
    public function getBeginOfWeek($year, $week)
    {
        $offset = date('w', mktime(0, 0, 0, 1, 1, $year));
        $offset = ($offset < 5) ? 1 - $offset : 8 - $offset;
        $monday = mktime(0, 0, 0, 1, 1 + $offset, $year);
        return strtotime('+' . ($week - 1) . ' weeks', $monday);
    }

    /**
     * Konvertiert Öffnungszeiten im Googleformat ins MJ-Format
     *
     * @param $sHours
     * @return string
     */
    public function convertGoogleOpenings($sHours)
    {
        $hourPattern = array(
            '#([^0-9:]|^)1:([0-9]{1,2}:[0-9]{1,2}):([0-9]{1,2}:[0-9]{1,2})([^0-9:]|$)#',
            '#([^0-9:]|^)2:([0-9]{1,2}:[0-9]{1,2}):([0-9]{1,2}:[0-9]{1,2})([^0-9:]|$)#',
            '#([^0-9:]|^)3:([0-9]{1,2}:[0-9]{1,2}):([0-9]{1,2}:[0-9]{1,2})([^0-9:]|$)#',
            '#([^0-9:]|^)4:([0-9]{1,2}:[0-9]{1,2}):([0-9]{1,2}:[0-9]{1,2})([^0-9:]|$)#',
            '#([^0-9:]|^)5:([0-9]{1,2}:[0-9]{1,2}):([0-9]{1,2}:[0-9]{1,2})([^0-9:]|$)#',
            '#([^0-9:]|^)6:([0-9]{1,2}:[0-9]{1,2}):([0-9]{1,2}:[0-9]{1,2})([^0-9:]|$)#',
            '#([^0-9:]|^)7:([0-9]{1,2}:[0-9]{1,2}):([0-9]{1,2}:[0-9]{1,2})([^0-9:]|$)#',
            '#,$#'
        );

        $hourReplacement = array(
            '${1}So ${2}-${3}${4}',
            '${1}Mo ${2}-${3}${4}',
            '${1}Di ${2}-${3}${4}',
            '${1}Mi ${2}-${3}${4}',
            '${1}Do ${2}-${3}${4}',
            '${1}Fr ${2}-${3}${4}',
            '${1}Sa ${2}-${3}${4}',
            ''
        );

        return preg_replace($hourPattern, $hourReplacement, $sHours);
    }

    /**
     * Ermittelt den nächsten regulären Arbeitstag (ohne Feiertage)
     *
     * @param string $date Startdatum, von dem ausgegangen werden soll
     * @param string $intervallLength Intervall, welches auf das Startdatum addiert werden soll
     * @param string $intervallType Intervalltyp (Tag, Monat, Jahr ...)
     *
     * @return bool|string
     */
    public function findNextWorkDay($date, $intervallLength, $intervallType = 'day')
    {
        $weekDay = date('w', strtotime($date . ' + ' . $intervallLength . ' ' . $intervallType));
        $strDays = '';
        switch ($weekDay) {
            case '0':
            {
                $strDays = ' -2days';
                break;
            }
            case '6':
            {
                $strDays = ' -1days';
                break;
            }
            default:
            {

            }
        }

        $nextDate = date('Y-m-d H:i:s', strtotime($date . ' + ' . $intervallLength . $intervallType . ' ' . $strDays));

        return $nextDate;
    }

    /**
     * Liefert das Datum eines spezifischen Wochentages einer Woche eines Jahres
     *
     * @param int $year Jahr
     * @param int $week Kalenderwoche
     * @param string $weekDay Wochentag (Mo-So)
     *
     * @return bool|string
     */
    public function findDateForWeekday($year, $week, $weekDay)
    {
        $aDates = array(
            'Mo' => date('d.m.Y', $this->getBeginOfWeek($year, $week)),
            'Di' => date('d.m.Y', strtotime('+ 1 day', $this->getBeginOfWeek($year, $week))),
            'Mi' => date('d.m.Y', strtotime('+ 2 day', $this->getBeginOfWeek($year, $week))),
            'Do' => date('d.m.Y', strtotime('+ 3 day', $this->getBeginOfWeek($year, $week))),
            'Fr' => date('d.m.Y', strtotime('+ 4 day', $this->getBeginOfWeek($year, $week))),
            'Sa' => date('d.m.Y', strtotime('+ 5 day', $this->getBeginOfWeek($year, $week))),
            'So' => date('d.m.Y', strtotime('+ 6 day', $this->getBeginOfWeek($year, $week))),
        );

        foreach ($aDates as $dayKey => $dayValue) {
            if (preg_match('#' . $weekDay . '#', $dayKey)) {
                return $dayValue;
            }
        }

        return false;
    }

    /**
     * Delivers the Date from the Weekday with weekly delay
     * @param string $weekday
     * @param int $weekDelay
     * @return false|string
     */
    public function findDateForWeekdayWithDelay($weekday, $weekDelay = 0)
    {
        $delay = intval($weekDelay);
        $sTime = "this week %$weekday% + $delay weeks";
        $preparedTimeString = strtr($sTime, [
            '%Mo%' => 'Monday',
            '%Di%' => 'Tuesday',
            '%Mi%' => 'Wednesday',
            '%Do%' => 'Thursday',
            '%Fr%' => 'Friday',
            '%Sa%' => 'Saturday',
            '%So%' => 'Sunday',
        ]);
        return date('d.m.Y', strtotime($preparedTimeString));
    }

    /**
     * Delivers the Number of the Week from the current Number of the Week with weekly delay
     * @param int $weekDelay
     * @param string $format
     * @return false|string
     */
    public function findWeekWithDelay($weekDelay, $format = 'W')
    {
        $delay = intval($weekDelay);
        return date($format, strtotime("this week Thursday + $delay weeks"));
    }

    /**
     * get WeekNumber
     * @param string $whichWeek
     * @return false|string
     */
    public function getWeekNr($whichWeek = 'this')
    {
        return date('W', strtotime("$whichWeek week Thursday"));
    }

    /**
     * get Year
     * @param string $whichWeek
     * @param bool $onlyTwoDigits
     * @return false|string
     */
    public function getWeeksYear($whichWeek = 'this', $onlyTwoDigits = false)
    {
        return date(($onlyTwoDigits ? 'y' : 'Y'), strtotime("$whichWeek week Thursday"));
    }

    /**
     * Funktion um englische in deutsche Wochentage zu konvertieren
     *
     * @param string $strTime
     * @return string
     */
    public function convertToGermanDays($strTime)
    {
        $aEnglishDayPattern = array(
            '#Mo.*?\s*([^A-Za-z])#i',
            '#Tu.*?\s*([^A-Za-z])#i',
            '#We.*?\s*([^A-Za-z])#i',
            '#Th.*?\s*([^A-Za-z])#i',
            '#Fr.*?\s*([^A-Za-z])#i',
            '#Sa.*?\s*([^A-Za-z])#i',
            '#Su.*?\s*([^A-Za-z])#i'
        );

        $aGermanDayReplacement = array(
            'Mo $1',
            'Di $1',
            'Mi $1',
            'Do $1',
            'Fr $1',
            'Sa $1',
            'So $1'
        );

        return preg_replace($aEnglishDayPattern, $aGermanDayReplacement, $strTime);
    }

    /**
     * Funktion um Monatsnummer aus -namen zu ermitteln
     *
     * @param string $strMonthName
     * @return string
     */
    public function findNumberForMonth($strMonthName)
    {
        foreach ($this->_aMonthName as $monthNameKey => $monthNameValue) {

            if (!is_array($monthNameValue) && preg_match($monthNameValue, $strMonthName)) {
                return $monthNameKey;
            }
        }
        return false;
    }

    /**
     * Funktion, ob zu prüfen ob Crontab gerade "wahr" ist
     *
     * @param string $strCron
     * @return boolean
     */
    public function checkCron($strCron)
    {
        $now = explode(' ', date('i H d m N'));
        $cron = $this->_parseCron($strCron);

        if (in_array($now[0], $cron[0])
            && in_array($now[1], $cron[1])
            && in_array($now[2], $cron[2])
            && in_array($now[3], $cron[3])
            && in_array($now[4], $cron[4])) {
            return true;
        }
        return false;
    }

    /**
     *
     * @param string $cron
     * @return array|bool
     */
    protected function _parseCron($cron)
    {
        $cron = explode(' ', trim($cron));
        if (count($cron) != 5) {
            return false;
        }

        $minutes = $this->_parseCronPart($cron[0], 0, 59);
        $hours = $this->_parseCronPart($cron[1], 0, 23);
        $days = $this->_parseCronPart($cron[2], 1, 31);
        $months = $this->_parseCronPart($cron[3], 1, 12);
        $weekdays = $this->_parseCronPart($cron[4], 0, 7);
        if (!$minutes || !$hours || !$days || !$months || !$weekdays
        ) {
            return false;
        }
        if (in_array(0, $weekdays) && !in_array(7, $weekdays)) {
            $weekdays[] = 7;
        }

        return array($minutes, $hours, $days, $months, $weekdays);
    }

    /**
     * Liefert ein Array mit allen möglichen Werten einer bestimmten Spalte eines Crontabs.
     * @param $part
     * @param $min
     * @param $max
     * @return array|bool
     */
    protected function _parseCronPart($part, $min, $max)
    {
        $values = array();
        $part = trim($part);
        if (preg_match('#^[0-9]+$#', $part)) { // einzelner Integer-Wert (12)
            if ($part < $min || $part > $max
            ) {
                return false;
            }
            $values[] = (int)$part;
        } else {
            if ($part == '*') { // alle erlaubten Werte der Spalte (*)
                for ($i = $min; $i <= $max; $i++) {
                    $values[] = $i;
                }
            } else if (preg_match('#\*/([0-9]+)#', $part, $match)) { // nur bestimmte Inkremente (*/5)
                for ($i = $min; $i <= $max; $i++) {
                    if ($i % $match[1] == 0) {
                        $values[] = $i;
                    }
                }
            } else if (preg_match('#^[0-9,]+$#', $part)) { // eine Liste von Werten (1,2,3)
                $parts = explode(',', $part);
                foreach ($parts as $part) {
                    if ($part >= $min && $part <= $max
                    ) {
                        $values[] = (int)$part;
                    }
                }
            } else if (preg_match('#^([0-9]+)-([0-9]+)$#', $part, $matches)) { // ein Bereich von Werten (1-5)
                for ($i = max($min, $matches[1]); $i <= min($max, $matches[2]); $i++) {
                    $values[] = (int)$i;
                }
            } else {
                // unbekannte Syntax
                return false;
            }
        }
        return $values;
    }

    /**
     *
     * @param string $strTime Uhrzeitenstring
     * @param string $localCode Ländercode
     * @return string
     */
    public function localizeDate($strTime, $localCode = 'DE')
    {
        $localCode = strtoupper(substr($localCode, 0, 2));
        if (preg_match('#FR#', $localCode)) {
            $strTime = preg_replace('#(\d{1,2})\s+#', '$1. ', $strTime);
            $strTime = iconv(mb_detect_encoding($strTime), 'ASCII//TRANSLIT', $strTime);
        }

        $aFrenchMonthsPattern = array(
            '#\s*janv.*?\s*([^A-Za-z])#i',
            '#\s*fev.*?\s*([^A-Za-z])#i',
            '#\s*mar.*?\s*([^A-Za-z])#i',
            '#\s*avr.*?\s*([^A-Za-z])#i',
            '#\s*mai.*?\s*([^A-Za-z])#i',
            '#\s*juin.*?\s*([^A-Za-z])#i',
            '#\s*juil.*?\s*([^A-Za-z])#i',
            '#\s*aou.*?\s*([^A-Za-z])#i',
            '#\s*sept.*?\s*([^A-Za-z])#i',
            '#\s*oct.*?\s*([^A-Za-z])#i',
            '#\s*nov.*?\s*([^A-Za-z])#i',
            '#\s*dec.*?\s*([^A-Za-z])#i',
        );

        $aGermanMonthsPattern = array(
            '#\s*jan.*?\s*([^A-Za-z])#i',
            '#\s*feb.*?\s*([^A-Za-z])#i',
            '#\s*mär.*?\s*([^A-Za-z])#i',
            '#\s*apr.*?\s*([^A-Za-z])#i',
            '#\s*mai.*?\s*([^A-Za-z])#i',
            '#\s*jun.*?\s*([^A-Za-z])#i',
            '#\s*jul.*?\s*([^A-Za-z])#i',
            '#\s*aug.*?\s*([^A-Za-z])#i',
            '#\s*sept.*?\s*([^A-Za-z])#i',
            '#\s*okt.*?\s*([^A-Za-z])#i',
            '#\s*nov.*?\s*([^A-Za-z])#i',
            '#\s*dez.*?\s*([^A-Za-z])#i',
        );

        $aEnglishMonthsReplacement = array(
            '01.$1',
            '02.$1',
            '03.$1',
            '04.$1',
            '05.$1',
            '06.$1',
            '07.$1',
            '08.$1',
            '09.$1',
            '10.$1',
            '11.$1',
            '12.$1',
        );

        $aTimeLanguages = array(
            'FR' => $aFrenchMonthsPattern,
            'DE' => $aGermanMonthsPattern,
        );

        return preg_replace($aTimeLanguages[$localCode], $aEnglishMonthsReplacement, $strTime);
    }

    /**
     * delivers the date, which get without Year, with a assumed Year
     * If format is set, it delivers the formated string
     *
     * @param $dayAndMonth
     * @param string|bool $format
     * @param string|bool $notBeforeThatDate
     * @return int|string
     */
    public function getDateWithAssumedYear($dayAndMonth, $format = false, $notBeforeThatDate = false)
    {
        $iYear = (int)date('Y');

        $notBeforeThatDate = $notBeforeThatDate ?: '- 6 months';
        $aNotBeforeDate = explode('.', $notBeforeThatDate);
        $aNotBeforeDate[2] = strlen($aNotBeforeDate[2]) == 2 ? "20$aNotBeforeDate[2]" : $aNotBeforeDate[2];

        $aDateAndMonth = explode('.', $dayAndMonth);
        $sDateAndMonth = $aDateAndMonth[0] . '.' . $aDateAndMonth[1] . '.';
        $assumedDate = strtotime($sDateAndMonth . $iYear);

        if ($assumedDate < strtotime($aNotBeforeDate[0] . '.' . $aNotBeforeDate[1] . '.' . $aNotBeforeDate[2])) {
            $assumedDate = strtotime($sDateAndMonth . ++$iYear);
        }
        return $format ? date($format, $assumedDate) : $assumedDate;
    }

    /**
     * @param string $date
     * @return bool
     */
    public function isDateAhead(string $date): bool
    {
        return strtotime($date) > time();
    }

    protected function getOpenHourWithoutDays(string $strTime, array $aGermanDayReplacement): string
    {
        $hours = [];
        foreach (explode('*', $strTime) as $i => $hour) {
            $hours[] = $aGermanDayReplacement[$i] . preg_replace('/[^0-9.: -]+/', '', $hour);
        }

        return str_replace('$1', '', implode( '*', $hours));
    }
}
