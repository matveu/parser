<?php

namespace matveu\parser;

require '/vendor/autoload.php';

class DomParser
{
    private $page;
    public $url;

    public function __construct($url)
    {
        $this->url = $url;
    }

    private function getPageData()
    {
        if (!isset($this->url) || !is_string($this->url)) {
            echo "Please, set valid URL!";
            exit();
        }

        $page = file_get_html($this->url);

        if (!$page) {
            echo "Can't get data from page. Please, check the entering URL and try again.";
            exit();
        }

        return $page;
    }

    private function curlGetContents($imgSrc)
    {
        $ch = curl_init($imgSrc);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
        $data = curl_exec($ch);
        curl_close($ch);

        return $data;
    }

    private function modifierPrependHttps($string, $subStr)
    {
        if (!empty($string) && !empty($subStr)) {
            $length = strlen($subStr);
            if (substr($string, 0, $length) === $subStr) {
                return 'https:' . $string;
            }

            return $string;
        } else {
            return '';
        }
    }

    public function run()
    {
        echo "Starting..." . PHP_EOL;
        $this->page = $this->getPageData();

        if (!file_exists(__DIR__ . '\parsed_img')) {
            mkdir(__DIR__ . '\parsed_img', 0777, true);
        }

        $countSavedImg = 0;
        $imageTypes = [
            IMAGETYPE_GIF  => '.gif',
            IMAGETYPE_JPEG => '.jpg',
            IMAGETYPE_PNG  => '.png'
        ];

        foreach ($this->page->find('img') as $img) {
            $img->src = $this->modifierPrependHttps($img->src, '//');
            if (!$this->curlGetContents($img->src)){ echo "Image url is wrong: $img->src" . PHP_EOL; continue;}
            if (!array_key_exists((exif_imagetype($img->src)) ?: 0, $imageTypes)) { echo "Incorrect image type: $img->src" . PHP_EOL; continue;}
            $imagePath = "\\parsed_img\\" . bin2hex(openssl_random_pseudo_bytes(10)) . $imageTypes[exif_imagetype($img->src)];
            if (!file_put_contents(__DIR__ . $imagePath, $this->curlGetContents($img->src), FILE_APPEND | LOCK_EX)) { echo "Can't write image to file: $img->src" . PHP_EOL; continue;}
            $countSavedImg += 1;
        }

        echo "Total saved images: $countSavedImg" . PHP_EOL;
        echo "Directory: " . __DIR__ . "\\parsed_img" . PHP_EOL;
    }
}