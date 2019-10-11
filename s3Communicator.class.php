<?php
//Pull in the secret sauce =)

include_once(dirname(__FILE__) . "/_settings.php");

// Installed From composer.json
require 'vendor/autoload.php';
use Aws\S3\S3Client;
use Aws\S3\Exception\S3Exception;
// /composer.json




class S3Communicator {
    private $s3Bucket;
    private $s3CloudFrontURL;
    private $s3;

    //--------------------------------------------------------------------
    // CONSTRUCT
    //--------------------------------------------------------------------
    function __construct($bucketName, $cloudFrontURL) {
      $this->s3Bucket = $bucketName;
      $this->s3CloudFrontURL = $cloudFrontURL;

      $this->s3 = new S3Client([
          'version' => 'latest',
          'region'  => S3_REGION,
          'credentials' => [
            'key'    => S3_KEY,
            'secret' => S3_SECRET,
          ]
      ]);
    }

    //--------------------------------------------------------------------
    // DESTRUCT
    //--------------------------------------------------------------------
    function __destruct() {
      //
    }

    //--------------------------------------------------------------------
    // PUT
    //--------------------------------------------------------------------
    function upload_image($formTmpName, $newFileName = null) {
      $milliseconds = round(microtime(true) * 1000);
      $myFileName = (!$newFileName) ? $milliseconds . ".jpg" : $newFileName;
      try {
        $upload = $this->s3->putObject(
          [
            'Bucket' => $this->s3Bucket,
            'Key' => $myFileName,
            'SourceFile' => $formTmpName,
            'ContentType' => 'image/jpg'
          ]
        );
      } catch (Exception $e) {
        echo 'Caught exception: ',  $e->getMessage(), "\n";
      }
      return $upload;
    }

    //--------------------------------------------------------------------
    // GET BUCKET NAME
    //--------------------------------------------------------------------
    function get_bucket_name() {
      return $this->s3Bucket;
    }

}
