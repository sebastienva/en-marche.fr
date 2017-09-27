<?php

namespace AppBundle\Referent;

use AppBundle\Entity\Committee;
use AppBundle\Intl\FranceCitiesBundle;

class ManagedAreaUtils
{
    const CODE_CORSICA_A = '2A';
    const CODE_CORSICA_B = '2B';
    const CODE_FRANCE = 'FR';
    const CODE_MONACO = '06';
    const POSTALCODE_MONACO = '98000';
    const PREFIX_POSTALCODE_CORSICA = '20';
    const PREFIX_POSTALCODE_CORSICA_A = ['200', '201'];
    const PREFIX_POSTALCODE_DOM = '97';
    const PREFIX_POSTALCODE_PARIS_DISTRICTS = '75';
    const PREFIX_POSTALCODE_TOM = '98';

    public static function getCodeFromCommittee(Committee $committee): string
    {
        if (self::CODE_FRANCE === $committee->getCountry()) {
            return static::getCodeFromPostalCode($committee->getPostalCode());
        } else {
            return static::getCodeFromCountry($committee->getCountry());
        }
    }

    public static function getCodeFromPostalCode(string $postalCode): string
    {
        $department = substr($postalCode, 0, 2);

        switch ($department) {
            case self::PREFIX_POSTALCODE_PARIS_DISTRICTS:
                return $postalCode;

                break;
            case self::PREFIX_POSTALCODE_TOM:
                if (self::POSTALCODE_MONACO === $postalCode) {
                    return self::CODE_MONACO;
                }

                return substr($postalCode, 0, 3);

                break;
            case self::PREFIX_POSTALCODE_DOM:
                return substr($postalCode, 0, 3);

                break;
            case self::PREFIX_POSTALCODE_CORSICA:
                if (in_array(substr($postalCode, 0, 3), self::PREFIX_POSTALCODE_CORSICA_A)) {
                    return self::CODE_CORSICA_A;
                } else {
                    return self::CODE_CORSICA_B;
                }

                break;
            default:
                return $department;

                break;
        }
    }

    public static function getCodeFromCountry(string $country): string
    {
        if (in_array($country, FranceCitiesBundle::$countries)) {
            $code = array_search($country, FranceCitiesBundle::$countries);

            if (self::POSTALCODE_MONACO === $code) {
                return self::CODE_MONACO;
            }

            return $code;
        }

        return $country;
    }
}
