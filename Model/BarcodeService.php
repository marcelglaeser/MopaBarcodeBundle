<?php
namespace Mopa\Bundle\BarcodeBundle\Model;

use Symfony\Component\DependencyInjection\Container;
use Monolog\Logger;
use Imagine\Gd\Image;
use Imagine\Image\ImagineInterface;
use Imagine\Image\Box;
use Imagine\Image\Metadata\MetadataBag;
use Imagine\Image\Palette\RGB as RGBColor; //PaletteInterface
use Zend\Barcode\Barcode;

/**
 * Class BarcodeService
 * @package Mopa\Bundle\BarcodeBundle\Model
 */
class BarcodeService{
    /**
     * @var array
     */
    private $types;
    
    /**
     * @var Container
     */
    private $container;

    /**
     * @var ImagineInterface
     */
    private $imagine;

    /**
     * @var string
     */
    private $kernelcachedir;

    /**
     * @var string
     */
    private $kernelrootdir;

    /**
     * @var string
     */
    private $webdir;

    /**
     * @varstring
     */
    private $webroot;

    /**
     * @var string
     */
    private $overlayPath;

    /**
     * @var Logger
     */
    private $logger;
    
    /**
     * @param Container $container
     * @param ImagineInterface $imagine
     * @param $kernelcachedir
     * @param $kernelrootdir
     * @param $webdir
     * @param $webroot
     * @param Logger $logger
     */
    public function __construct(Container $container, ImagineInterface $imagine, $kernelcachedir, $kernelrootdir, $webdir, $webroot, Logger $logger){
        $this->types = BarcodeTypes::getTypes();
        $this->container = $container;
        $this->imagine = $imagine;
        $this->kernelcachedir = $kernelcachedir;
        $this->kernelrootdir = $kernelrootdir;
        $this->webdir = $webdir;
        $this->webroot = $webroot;
        $this->logger = $logger;
        $this->getOverlayPath();
    }

    /**
     * @param $type
     * @param $text
     * @param $file
     * @param array $options
     * @return bool
     */
    public function saveAs($type, $text, $file, $options = array()){
        @unlink($file);
        switch ($type){
            case $type == 'qr':
                include_once __DIR__.DIRECTORY_SEPARATOR."..".DIRECTORY_SEPARATOR."Resources".DIRECTORY_SEPARATOR."phpqrcode".DIRECTORY_SEPARATOR."qrlib.php";

                $level = (isset($options['level'])) ? $options['level'] : QR_ECLEVEL_L;
                $size = (isset($options['size'])) ? $options['size'] : 3;
                $margin = (isset($options['margin'])) ? $options['margin'] : 4;
                \QRcode::png($text, $file, $level, $size, $margin);

                if (isset($options['useOverlay']) && $options['useOverlay']) {
                    $this->addOverlay($file, $size);
                }

            break;
            case is_numeric($type):
                $type = $this->types[$type];
            default:
                $barcodeOptions = array_merge(isset($options['barcodeOptions']) ? $options['barcodeOptions'] : array(), array('text' => $text));
                $rendererOptions = isset($options['rendererOptions']) ? $options['rendererOptions'] : array();
                $rendererOptions['width'] = isset($rendererOptions['width']) ? $rendererOptions['width'] : 2233;
                $rendererOptions['height'] = isset($rendererOptions['height']) ? $rendererOptions['height'] : 649;
                $palette = new RGBColor();
                $metaData = new MetadataBag();
                $imageResource = Barcode::factory($type, 'image', $barcodeOptions, $rendererOptions)->draw();
                $image = new Image($imageResource, $palette, $metaData);
                $image->save($file);
        }
        return true;
    }

    /**
     * @param $file
     * @param $size
     */
    private function addOverlay($file, $size)
    {
        list($width) = getimagesize($file);
        $size = ($size < 1) ? 1 : $size;
        $originalLevelWidth = $width / $size;

        $overlayImagePath = $this->overlayPath . DIRECTORY_SEPARATOR . $originalLevelWidth . '.png';

        if (file_exists($overlayImagePath)) {
            $destination = imagecreatefrompng($file);
            $src = imagecreatefrompng($overlayImagePath);

            list($src_width) = getimagesize($overlayImagePath);
            $overlayImageWidth = $src_width;

            #$palette = new RGBColor();
            #$metaData = new MetadataBag();
            #$overlayImage = new Image($src, $palette, $metaData);
            #$overlayImage->resize(new Box($overlayImageWidth, $overlayImageWidth));
            // $src = $overlayImage;
            #$thumb = Imagick('myimage.gif');

            /* $new_image = imagecreatetruecolor($overlayImageWidth, $overlayImageWidth);
              $white = imagecolorallocate($new_image, 0, 0, 0);
              imagefill($new_image, 0, 0, $white);
              imagecolortransparent($new_image, $white);

              imagecopyresized($new_image, $src, 0, 0, 0, 0, $overlayImageWidth, $overlayImageWidth, imagesx($src), imagesy($src));

              $src = $new_image;
             */
            /* $palette = new RGBColor();
              $metaData = new MetadataBag();
              $overlayImage = new Image($src, $palette, $metaData);
              $overlayImage->resize(new Box($overlayImageWidth, $overlayImageWidth));
              $tmpFilePath = $this->kernelcachedir . DIRECTORY_SEPARATOR . sha1(time() . rand()) . '.png';
              $overlayImage->save($tmpFilePath);
              $src = imagecreatefrompng($tmpFilePath);
             */



            $xoffset = ($width - $overlayImageWidth) / 2;
            $yoffset = ($width - $overlayImageWidth) / 2;

            imagecopymerge($destination, $src, $xoffset, $yoffset, 0, 0, $overlayImageWidth, $overlayImageWidth, 100);
            #imagepng($src, $file, 0);
            imagepng($destination, $file, 9);
            imagedestroy($destination);
            imagedestroy($src);
            //unlink($tmpFilePath);
        }
    }
    
    /**
     * @param $dst_im
     * @param $src_im
     * @param $dst_x
     * @param $dst_y
     * @param $src_x
     * @param $src_y
     * @param $src_w
     * @param $src_h
     * @param $pct
     */
    private function imagecopymerge_alpha($dst_im, $src_im, $dst_x, $dst_y, $src_x, $src_y, $src_w, $src_h, $pct){
        // creating a cut resource
        $cut = imagecreatetruecolor($src_w, $src_h);

        // copying relevant section from background to the cut resource
        imagecopy($cut, $dst_im, 0, 0, $dst_x, $dst_y, $src_w, $src_h);

        // copying relevant section from watermark to the cut resource
        imagecopy($cut, $src_im, 0, 0, $src_x, $src_y, $src_w, $src_h);

        // insert cut resource to destination image
        imagecopymerge($dst_im, $cut, $dst_x, $dst_y, 0, 0, $src_w, $src_h, $pct);
    }


    /**
     * Get a Barcodes Filename
     * Generates it if its not here
     *
     * @param string $type BarcodeType
     * @param string $enctext BarcodeText
     * @param boolean $absolute get absolute path, default: false
     * @param array $options Options
     * @return mixed|string
     */
    public function get($type, $enctext, $absolute = false, $options = array()){
        $text = urldecode($enctext);
        $filename = $this->getAbsoluteBarcodeDir($type).$this->getBarcodeFilename($text, $options);

        if(
            (isset($options['noCache']) && $options['noCache'])
            || !file_exists($filename)
          ) {
            $this->saveAs($type, $text, $filename, $options);
        }

        if(!$absolute){
            $path = DIRECTORY_SEPARATOR.$this->webdir.$this->getTypeDir($type).$this->getBarcodeFilename($text, $options);
            return str_replace(DIRECTORY_SEPARATOR, "/", $path);
        }

        return $filename;
    }

    /**
     * @param $type
     * @return string
     */
    protected function getTypeDir($type){
        if(is_numeric($type)){
            $type = $this->types[$type];
        }
        return $type.DIRECTORY_SEPARATOR;
    }

    /**
     * @param $text
     * @param $options
     * @return string
     */
    protected function getBarcodeFilename($text, $options){
        return sha1($text . serialize($options)).".png";
    }

    /**
     * @param $type
     * @return string
     */
    protected function getAbsoluteBarcodeDir($type){
        $path = $this->getAbsolutePath().$this->getTypeDir($type);
        if(!file_exists($path)){
            mkdir($path, 0777, true);
        }
        return $path;
    }

    /**
     * @return string
     */
    protected function getAbsolutePath(){
        return $this->webroot.DIRECTORY_SEPARATOR.$this->webdir;
    }

    /**
     * @return string
     */
    protected function getOverlayPath()
    {
        $overlayPath = $this->container->getParameter('mopa_barcode.overlay_images_path');
        if ($overlayPath) {
            $this->overlayPath = $overlayPath;
        } else {
            $this->overlayPath = __DIR__ . '/../Resources/qr_overlays';
        }
    }
}
