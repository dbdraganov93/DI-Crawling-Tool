<?php

namespace Marktjagd\Service\IprotoApi;

/**
 * Returns an API-service which provides all input and output functionality for either the APIv3 or iProto.
 * This static class can be used to configure the correct API-client either globally or overwrite the setting on
 * a crawler-basis.
 */
class ApiServiceProvider
{
    public const IPROTO = 'iproto';
    public const API3 = 'api3';

    protected static string $defaultApi = self::IPROTO;

    public static function setDefaultApi(string $api): void
    {
        self::$defaultApi = $api;
    }

    public static function getDefaultApi(): string
    {
        return self::$defaultApi;
    }

    protected static string $defaultEnv = APPLICATION_ENV;

    public static function setDefaultEnv(string $env): void
    {
        self::$defaultEnv = $env;
    }

    public static function getDefaultEnv(): string
    {
        return self::$defaultEnv;
    }

    public static function getApiService(?string $apiType=null, ?string $apiEnv=null): ApiServiceInterface
    {
        if ($apiType === null) $apiType = self::$defaultApi;
        if ($apiEnv === null) $apiEnv = self::$defaultEnv;
        if ($apiType == self::API3) return new ApiServiceApi3(); // XXX: The APIv3 key/env is currently determined by the CompanyID/PartnerID stored in the DB and is not changeable
        else if ($apiType == self::IPROTO) return new ApiServiceIproto($apiEnv);
        else throw new \RuntimeException("unexpected api-type $apiType");
    }
}
