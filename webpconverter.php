<?php
defined('_JEXEC') or die;

use Joomla\CMS\Plugin\CMSPlugin;
use Joomla\CMS\Factory;
use Joomla\CMS\Uri\Uri;

class PlgSystemWebpconverter extends CMSPlugin {

    public function onAfterRender() {
        $app = Factory::getApplication();
        if ($app->isClient('administrator') || $app->input->get('format') == 'raw') return;

        $body = $app->getBody();
        
        // Cache-Logik: Wir erstellen eine ID basierend auf dem Seiteninhalt
        $cache = Factory::getCache('plg_sys_webpconverter', 'output');
        $cacheId = md5($body . Uri::base());

        // Wenn die optimierte Seite im Cache liegt, nimm diese
        if ($cachedBody = $cache->get($cacheId)) {
            $app->setBody($cachedBody);
            return;
        }

        $regex = '/(src|href|data-thumb|url)\s*[=:]\s*["\'(]([^"\'()]+\.(jpe?g|png))["\')]?/i';
        preg_match_all($regex, $body, $matches);

        if (!empty($matches[2])) {
            $baseUri = Uri::base();
            $basePath = JPATH_SITE;
            $changed = false;

            foreach ($matches[2] as $imagePath) {
                if (strpos($imagePath, 'http') === 0 && strpos($imagePath, Uri::base()) === false) continue;

                $localRelPath = $imagePath;
                if (strpos($localRelPath, $baseUri) === 0) {
                    $localRelPath = str_replace($baseUri, '', $localRelPath);
                }
                $localRelPath = ltrim($localRelPath, '/');

                $basePathFolder = trim(Uri::base(true), '/');
                if ($basePathFolder && strpos($localRelPath, $basePathFolder . '/') === 0) {
                    $localRelPath = substr($localRelPath, strlen($basePathFolder) + 1);
                }

                $fullPath = $basePath . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $localRelPath);
                $webpPath = preg_replace('/\.(jpe?g|png)$/i', '.webp', $fullPath);

                if (file_exists($fullPath)) {
                    if (!file_exists($webpPath)) {
                        $this->processImage($fullPath, $webpPath);
                    }
                    
                    if (file_exists($webpPath)) {
                        $newUrl = preg_replace('/\.(jpe?g|png)$/i', '.webp', $imagePath);
                        $body = str_replace($imagePath, $newUrl, $body);
                        $changed = true;
                    }
                }
            }
            
            if ($changed) {
                // Das optimierte Ergebnis für die Zukunft speichern
                $cache->store($body, $cacheId);
                $app->setBody($body);
            }
        }
    }

    private function processImage($source, $destination) {
        $info = @getimagesize($source);
        if (!$info) return false;
        
        $srcImage = ($info['mime'] == 'image/jpeg') ? @imagecreatefromjpeg($source) : @imagecreatefrompng($source);
        if (!$srcImage) return false;

        if ($info['mime'] == 'image/png') {
            imagepalettetotruecolor($srcImage);
            imagealphablending($srcImage, true);
            imagesavealpha($srcImage, true);
        }

        $width = imagesx($srcImage);
        $max_width = 1280;

        if ($width > $max_width) {
            $height = imagesy($srcImage);
            $new_width = $max_width;
            $new_height = floor($height * ($max_width / $width));
            $dstImage = imagecreatetruecolor($new_width, $new_height);
            imagealphablending($dstImage, false);
            imagesavealpha($dstImage, true);
            imagecopyresampled($dstImage, $srcImage, 0, 0, 0, 0, $new_width, $new_height, $width, $height);
            imagewebp($dstImage, $destination, 80);
            imagedestroy($dstImage);
        } else {
            imagewebp($srcImage, $destination, 80);
        }
        imagedestroy($srcImage);
        return true;
    }
}