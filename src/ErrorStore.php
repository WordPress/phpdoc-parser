<?php

namespace Aivec\Plugins\DocParser;

use AVCPDP\Aivec\ResponseHandler\ErrorStore as ES;
use AVCPDP\Aivec\ResponseHandler\GenericError;

/**
 * Error store
 */
class ErrorStore extends ES
{
    const REQUIRED_FIELDS_MISSING = 'RequiredFieldsMissing';
    const SOURCE_NOT_FOUND = 'SourceNotFound';
    const IMPORT_ERROR = 'ImportError';
    const JWT_UNAUTHORIZED = 'JWTUnauthorized';
    const BASE64_DECODE_ERROR = 'Base64DecodeError';
    const TEMP_ZIPFILE_WRITE_ERROR = 'TempZipFileWriteError';

    /**
     * Adds errors to the store
     *
     * @author Evan D Shaw <evandanielshaw@gmail.com>
     * @return void
     */
    public function populate() {
        $this->addError(new GenericError(
            self::REQUIRED_FIELDS_MISSING,
            $this->getConstantNameByValue(self::REQUIRED_FIELDS_MISSING),
            400,
            function ($field) {
                // translators: name of the missing field
                return sprintf(__('"%s" is required', 'wp-parser'), $field);
            },
            function ($message) {
                return $message;
            }
        ));

        $em = function ($name) {
            // translators: name of the plugin/theme/composer-package
            return sprintf(__('"%s" does not exist', 'wp-parser'), $name);
        };
        $this->addError(new GenericError(
            self::SOURCE_NOT_FOUND,
            $this->getConstantNameByValue(self::SOURCE_NOT_FOUND),
            404,
            $em,
            $em
        ));

        $this->addError(new GenericError(
            self::IMPORT_ERROR,
            $this->getConstantNameByValue(self::IMPORT_ERROR),
            422,
            function ($message) {
                return $message;
            },
            function ($message) {
                return $message;
            }
        ));
    }
}
