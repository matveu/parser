<?php

namespace matveu\parser;

class DomParser
{
    private $page;
    private $host;
    private $scheme;
    private $url;
    private $visitedPages = [];
    private $pathToFolder;

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
        $this->init();
    }

    private function init()
    {
        $this->parseUrl();
        $this->createFolder();
    }

    private function getPageData()
    {
        $page = file_get_html($this->url);

        if (!$page) {
            throw new \ErrorException("Can't get data from page: $this->url");
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

    private function curlUrlExists($url)
    {
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_HEADER, 1);
        curl_setopt($ch, CURLOPT_NOBODY, 1);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 0);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        $status = [];
        preg_match('/HTTP\/.* ([0-9]+) .*/', curl_exec($ch), $status);

        return ($status[1] == 200);
    }

    private function parseUrl()
    {
        $this->scheme   = parse_url($this->url, PHP_URL_SCHEME);
        $this->host     = parse_url($this->url, PHP_URL_HOST);
    }

    private function getAbsoluteSrc($src)
    {
        if (substr($src, 0, 2) === '//') {
            return $this->scheme . ':' . $src;
        } elseif (substr($src, 0, 1) === '/') {
            return $this->scheme . '://' . $this->host . $src;
        }

        return $src;
    }

    private function createFolder()
    {
        if (!file_exists(__DIR__ . '\parsed_img')) {
            if (!mkdir(__DIR__ . '\parsed_img', 0777, true)) {
                throw new \ErrorException("Can't create new folder in: " . __DIR__);
            };
        }
        $this->pathToFolder = __DIR__ . '\parsed_img';
    }

    private function parsePage()
    {
        echo "Starting... " . $this->url, PHP_EOL;

        $this->visitedPages[] = $this->url;
        $this->getPageData();
        $this->saveImages();
        $this->goToAnotherPage();
    }

    private function saveImages()
    {
        $qtySavedImg = 0;

        foreach ($this->page->find('img') as $img) {
            if (empty($img->src)) {
                echo "Image src is empty.", PHP_EOL;
                continue;
            }

            $img->src = $this->getAbsoluteSrc($img->src);

            if (!$this->curlGetContents($img->src)) {
                echo "Image src is wrong: $img->src", PHP_EOL;
                continue;
            }
            if (!array_key_exists((exif_imagetype($img->src)) ?: 0, self::IMAGE_TYPES)) {
                echo "Incorrect image type: $img->src", PHP_EOL;
                continue;
            }

            $imageName = bin2hex(openssl_random_pseudo_bytes(10)) . self::IMAGE_TYPES[exif_imagetype($img->src)];

            if (!file_put_contents($this->pathToFolder . '\\' . $imageName, $this->curlGetContents($img->src), FILE_APPEND | LOCK_EX)) {
                echo "Can't save image to folder: $img->src", PHP_EOL;
                continue;
            }

            $qtySavedImg += 1;
        }

        echo "Number of saved images: $qtySavedImg", PHP_EOL;
    }

    private function goToAnotherPage()
    {
        foreach ($this->page->find('a') as $link) {
            if (!$this->checkLink($link->href)) {
                continue;
            }
            $this->parsePage();
        }
    }

    private function checkLink($link)
    {
        if (substr($link, 0, 2) === '//') {
            $fullLink = $this->scheme . ":" . $link;
        } elseif (substr($link, 0, 1) === '/') {
            $fullLink = $this->scheme . "://" . $this->host . $link;
        } elseif (strpos($link, $this->host) !== false) {
            $fullLink = $link;
        } else {
            return false;
        }

        return (!in_array($fullLink, $this->visitedPages)) ? (($this->curlUrlExists($fullLink)) ? $this->url = $fullLink : false) : false;
    }

    public function run()
    {
        $this->parsePage();

        echo "Path to saved pictures: $this->pathToFolder", PHP_EOL;
    }
}