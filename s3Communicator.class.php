<?php
//Pull in the secret sauce =)

include_once(dirname(__FILE__) . "/_settings.php");

// Installed From composer.json
require 'vendor/autoload.php';

//for s3
use Aws\S3\S3Client;
use Aws\S3\Exception\S3Exception;

//for cloudfront
use Aws\CloudFront\CloudFrontClient; 
use Aws\Exception\AwsException;
// /composer.json




class S3Communicator {
    private $s3Bucket;
    private $s3CloudFrontURL;
    private $s3;
    private $s3CloudFrontDistributionID;

    private $cF;

    //--------------------------------------------------------------------
    // CONSTRUCT
    //--------------------------------------------------------------------
    function __construct($bucketName, $cloudFrontURL, $cloudFrontDistID) {
      $this->s3Bucket = $bucketName;
      $this->s3CloudFrontURL = $cloudFrontURL;
      $this->s3CloudFrontDistributionID = $cloudFrontDistID;

      $this->s3 = new S3Client([
          'version' => 'latest',
          'region'  => S3_REGION,
          'credentials' => [
            'key'    => S3_KEY,
            'secret' => S3_SECRET,
          ]
      ]);

      $this->cF = Aws\CloudFront\CloudFrontClient::factory(array(
        'region' => S3_REGION,
        'version' => 'latest',
        'credentials' => [
          'key'    => S3_KEY,
          'secret' => S3_SECRET
        ]
      ));

    }

    //--------------------------------------------------------------------
    // DESTRUCT
    //--------------------------------------------------------------------
    function __destruct() {
      //
    }

    //--------------------------------------------------------------------
    // CHECK IF FILE EXISTS
    //--------------------------------------------------------------------
    function check_if_exists($theFileName) {
      $response = $this->s3->doesObjectExist($this->s3Bucket, $theFileName);
      return $response;
    }

    //--------------------------------------------------------------------
    // PUT
    //--------------------------------------------------------------------
    function upload_image($formTmpName, $newFileName = null) {
      $milliseconds = round(microtime(true) * 1000);

      //lets detect the REAL image type, not just assume from the filename.
      $myImageExt = $this->derive_image_extension($formTmpName);

      //if its not a jpg, lets convert it to a jpg
      if ($myImageExt != ".jpg") {
        $formTmpName = $this->convertImageToJPG($formTmpName, $myImageExt);
      }

      $myFileName = (!$newFileName) ? $milliseconds . ".jpg" : $newFileName;

      $invalidation = null;
      $upload = null;

      //first, lets check if the file exists. if it does, we need to kick off an invalidation after we re-upload this image.
      $fileAlreadyExists = $this->check_if_exists($myFileName);

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

      $invalidation = ($fileAlreadyExists) ? $this->invalidate_image($myFileName) : "new file";

      $finalResponse = array(
        "invalidation" => $invalidation,
        "upload" => $upload
      );

      return $finalResponse;
    }

    //--------------------------------------------------------------------
    // DERIVE CORRECT IMAGE EXTENSION
    //--------------------------------------------------------------------
    function derive_image_extension($formTmpName) {
      $detectedType = exif_imagetype($formTmpName);

      switch ($detectedType) {
        case IMAGETYPE_JPEG:
          $imgExt = ".jpg";
          break;
        case IMAGETYPE_PNG:
          $imgExt = ".png";
          break;
        case IMAGETYPE_GIF:
          $imgExt = ".gif";
          break;
      }

      return $imgExt;
    }

    //--------------------------------------------------------------------
    // CONVERT IMAGE TO JPG
    //--------------------------------------------------------------------
    function convertImageToJPG($formTmpName, $imageType) {
      //might make this dynamic later, but for now lets hard-code 80
      $quality = 80;
      $milliseconds = round(microtime(true) * 1000);
      $outputImage = "/tmp/" . $milliseconds . ".jpg";

      switch ($imageType) {
        case ".png":
          $imageTmp = imagecreatefrompng($formTmpName);
          break;
        case ".gif":
          $imageTmp = imagecreatefromgif($formTmpName);
          break;

      }

      imagejpeg($imageTmp, $outputImage, $quality);

      return $outputImage;
    }

    //--------------------------------------------------------------------
    // INVALIDATE IMAGE IN CLOUDFRONT
    //--------------------------------------------------------------------
    function invalidate_image($theFileName) {
      $callerReference = round(microtime(true) * 1000);

      try {
        $result = $this->cF->createInvalidation([
          'DistributionId' => $this->s3CloudFrontDistributionID,
          'InvalidationBatch' => [
            'CallerReference' => $callerReference,
            'Paths' => [
              'Items' => ['/' . $theFileName],
              'Quantity' => 1,
            ],
          ]
        ]);
      } catch (AwsException $e) {
        // output error message if fails
        echo $e->getMessage();
        echo "\n";
      }

     return $result;
    }

    //--------------------------------------------------------------------
    // GET BUCKET NAME
    //--------------------------------------------------------------------
    function get_bucket_name() {
      return $this->s3Bucket;
    }

}
