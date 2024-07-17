<?php

/**
 * Interface Login_Check_ControllerAccessManager
 */
interface Login_Check_ControllerAccessManager
{

    /**
     * Prüft, ob die Action $actionName für jeden oder nur für eingeloggte
     * Benutzer zugänglich ist.
     *
     * $actionName enthält den Namen der angeforderten Action, nicht den Namen
     * der Methode.
     *
     * @param string $actionName
     * @return boolean true, wenn der Zugriff auf die Action für jeden erlaubt ist, sonst false.
     */
    public function isPublic($actionName);
}