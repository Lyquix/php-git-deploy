<?php

interface Plugin
{
    /**
     * successWebhook
     *
     * @param [array] $params information passed down to the plugin
     * @return bool|string Returns true if no errors occured, else returns error message
     */
    public static function successWebhook($params);

    /**
     * errorWebhook
     *
     * @param [string] $errorMessage the error message(s) passed down to the plugin
     * @return bool|string Returns true if no errors occured, else returns error message
     */
    public static function errorWebhook($errorMessage);
}