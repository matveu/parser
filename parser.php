<?php

namespace matveu\parser;

class DomParser
{
    private $page;
    private $host;
    private $scheme;
    private $url;

    const IMAGE_TYPES = [
        IMAGETYPE_GIF  => '.gif',
        IMAGETYPE_JPEG => '.jpg',
        IMAGETYPE_PNG  => '.png'
    ];

    public function __construct($url)
    {
        if (empty($url) || !is_string($url)) {
            throw new \ErrorException('Please, set valid URL!');
        }

        $this->url = $url;
    }

    private function getPageData()
    {
        $page = file_get_html($this->url);

        if (!$page) {
            throw new \ErrorException("Can't get data from page. Please, check the entering URL and try again.");
        }

        $this->page = $page;
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

    private function parseUrl()
    {
        $this->scheme   = parse_url($this->url, PHP_URL_SCHEME);
        $this->host     = parse_url($this->url, PHP_URL_HOST);
    }

    private function makeCorrectSrc($src)
    {
        if (substr($src, 0, 2) === '//') {
            return $this->scheme . ':' . $src;
        } elseif (substr($src, 0, 1) === '/') {
            return $this->scheme . '://' . $this->host . $src;
        }

        return $src;
    }

    private function createDir()
    {
        if (!file_exists(__DIR__ . '\parsed_img')) {
            if (!mkdir(__DIR__ . '\parsed_img', 0777, true)) {
                throw new \ErrorException("Can't create new directory in: " . __DIR__);
            };
        }
    }

    public function run()
    {
        echo "Starting..." , PHP_EOL;

        $this->getPageData();
        $this->createDir();
        $this->parseUrl();

        $countSavedImg = 0;

        foreach ($this->page->find('img') as $img) {
            if (empty($img->src)){ echo "Image src is empty." , PHP_EOL; continue;}

            $img->src = $this->makeCorrectSrc($img->src);

            if (!$this->curlGetContents($img->src)){ echo "Image src is wrong: $img->src" , PHP_EOL; continue;}
            if (!array_key_exists((exif_imagetype($img->src)) ?: 0, self::IMAGE_TYPES)) { echo "Incorrect image type: $img->src" , PHP_EOL; continue;}

            $imagePath = "\\parsed_img\\" . bin2hex(openssl_random_pseudo_bytes(10)) . self::IMAGE_TYPES[exif_imagetype($img->src)];

            if (!file_put_contents(__DIR__ . $imagePath, $this->curlGetContents($img->src), FILE_APPEND | LOCK_EX)) { echo "Can't write image to file: $img->src" , PHP_EOL; continue;}

            $countSavedImg += 1;
        }

        echo "Total saved images: $countSavedImg" , PHP_EOL;
        echo "Directory: " . __DIR__ . "\\parsed_img" , PHP_EOL;
    }
}