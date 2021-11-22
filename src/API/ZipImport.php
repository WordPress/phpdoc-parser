<?php

namespace Aivec\Plugins\DocParser\API;

use Aivec\Plugins\DocParser\ErrorStore;
use Aivec\Plugins\DocParser\Master;
use Aivec\Plugins\DocParser\Views\ImporterPage\ImporterPage;
use Aivec\Plugins\DocParser\REST\Import;
use AVCPDP\Aivec\ResponseHandler\GenericError;
use Aivec\Welcart\Extensions\Cptm\Admin\Settings\Settings;
use UnexpectedValueException;
use WCEXCPTM\Firebase\JWT\BeforeValidException;
use WCEXCPTM\Firebase\JWT\ExpiredException;
use WCEXCPTM\Firebase\JWT\SignatureInvalidException;
use AVCPDP\Firebase\JWT\JWT as FirebaseJWT;
use Exception;
use ZipArchive;

/**
 * Converts PHPDoc markup into a template ready for import to a WordPress blog.
 */
class ZipImport
{
    /**
     * Master object
     *
     * @var Master
     */
    private $master;

    /**
     * SHA256 public key string
     *
     * @var string
     */
    private $publicKey;

    /**
     * TestMehod
     *
     * @author Seiyu Inoue <s.inoue@aivec.co.jp>
     * @param Aivec\Plugins\DocParser\API\FunctionalTester $I
     */
    public function testZIpImport(FunctionalTester $I) {
        $sku = $I->getDomainUnrestrictedUniqueId();

        // ajaxParamSet
        $I->sendAjaxPostRequest('/', [
            'asmp_action' => 'zipImport',
            'jwt' => [
                'zipFileBase64' => $sku,
                'sourceName' => 'wcex-wishlist',
                'trashOldRefs' => true,
            ],
        ]);

        $I->seeResponseCodeIs(200);
        $I->seeResponseContains('success');
    }

    /**
     * Injects `$master`
     *
     * @author Evan D Shaw <evandanielshaw@gmail.com>
     * @param Master $master
     */
    public function __construct(Master $master) {
        $this->master = $master;

        $settings = ImporterPage::getSettings();
        $publicKey = $settings['sshPublicKeyValue'];
        $this->publicKey = $publicKey;
    }

    /**
     * Uploads a new ZIP archive to the `packages` directory for the given unique ID
     *
     * This API uses JWT RS256 authentication.
     *
     * @author Evan D Shaw <evandanielshaw@gmail.com>
     * @param array $args
     * @param array $payload Expects an array with the JWT payload set as the value of a key named `jwt`
     * @return string|GenericError Returns `success` on success
     */
    public function deployToUpdateServerPackagesDir($args, array $payload) {
        // SSH decode
        try {
            $data = $this->decode($payload['jwt']);
        } catch (Exception $e) {
            return $this->master->estore->getErrorResponse(ErrorStore::JWT_UNAUTHORIZED, [$e->getMessage()]);
        }

        // Get base64 zipfile
        $base64Field = 'zipFileBase64';
        if (empty($data[$base64Field])) {
            return $this->master->estore->getErrorResponse(
                ErrorStore::REQUIRED_FIELDS_MISSING,
                [$base64Field],
                ['The field "' . $base64Field . '" is required.']
            );
        }
        $base64 = $data[$base64Field];

        // Exchange blob
        $blob = base64_decode($base64);
        if (empty($blob)) {
            return $this->master->estore->getErrorResponse(ErrorStore::BASE64_DECODE_ERROR);
        }

        // Get source name
        $sourceNameField = 'sourceName';
        if (empty($data[$sourceNameField])) {
            return $this->master->estore->getErrorResponse(
                ErrorStore::REQUIRED_FIELDS_MISSING,
                [$sourceNameField],
                ['The field "' . $sourceNameField . '" is required.']
            );
        }
        $sourceName = $data[$sourceNameField];

        // Create zip file
        // 下の直解凍ができれば不要
        // $tempzip = '/' . trim(get_temp_dir(), '/') . '/' . $sourceName . '.zip';
        // $res = file_put_contents($tempzip, $blob);
        // if ($res === false) {
        //     return $this->master->estore->getErrorResponse(ErrorStore::TEMP_ZIPFILE_WRITE_ERROR, [$tempzip]);
        // }

        // todo:not test
        // blobを直解凍できるかな？？できたらする。
        // todo:zipの解凍処理phpの標準解凍処理を調べてみる
        $fpath = ImporterPage::getSettings()['sourceFoldersAbspath'];
        $zip = new ZipArchive();
        if ($zip->open($blob) === true) {
            $zip->extractTo($fpath . $sourceName);
            $zip->close();
        }

        // Create import instance
        $import = new Import($this->master);
        // Import package refarence
        $importArgs = (['fname' => $sourceName]);
        $importPayload = (['trashOldRefs' => $data['trashOldRefs']]);
        return $import->create($importArgs, $importPayload);
    }

    /**
     * Decodes a JWT payload
     *
     * @author Evan D Shaw <evandanielshaw@gmail.com>
     * @param string $payload
     * @return array
     * @throws UnexpectedValueException Firebase library exception.
     * @throws SignatureInvalidException Firebase library exception.
     * @throws BeforeValidException Firebase library exception.
     * @throws ExpiredException Firebase library exception.
     */
    public function decode($payload) {
        return (array)FirebaseJWT::decode($payload, $this->publicKey, ['RS256']);
    }
}
