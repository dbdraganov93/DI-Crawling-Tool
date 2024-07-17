*** LIBS ***
Das PDFNet-SDK besteht aus der Lib für PHP "PDFNetPHP.so", welche beim PHP-Aufruf
geladen wird (zB "/usr/bin/php -d extension=/home/pdftron/PDFNetPHP.so") sowie
der eigentlichen Lib von PDFTron "libPDFNetC.so". Diese Lib wird leider ab Version
6.0 nicht mehr automatisch im Ausführungsverzeichnis gefunden. Es kann entweder
unter "/etc/ld.so.conf.d" der Pfad systemweit hinzugefügt werden, oder der Pfad
wird in der ".bashrc" per export gesetzt. Die beste Möglichkeit ist es jedoch
vermutlich, den Pfad explizit beim Aufruf des benötigten Kommandos zu setzen, zB:

LD_LIBRARY_PATH=. ./extractData.php

Pdf2Image benötigt keine weiteren Libs.


*** UPDATES ***
Von der PDFTron-Webseite können einfach die Trial-Versionen heruntergeladen werden.
Durch das Beilegen der Lizenzschlüssel werden automatisch Vollversionen daraus.

