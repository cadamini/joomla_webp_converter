<?php
/**
 * @package     Joomla.Plugin
 * @subpackage  System.Webpconverter
 * @author      Christian Adamini - adamini@gmx.de
 * @copyright   Copyright (C) 2026 Christian Adamini. Alle Rechte vorbehalten.
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

defined('_JEXEC') or die;

use Joomla\CMS\Plugin\CMSPlugin;
use Joomla\CMS\Factory;
use Joomla\CMS\Uri\Uri;

class PlgSystemWebpconverter extends CMSPlugin {

    private $clearMsg = "";
    private $cacheDir = 'images/webp_cache';

    public function __construct(&$subject, $config) {
        parent::__construct($subject, $config);
        
        $app = Factory::getApplication();
        if ($app->isClient('administrator')) {
            if (isset($_GET['clearWebp']) && $_GET['clearWebp'] == '1') {
                $this->clearWebpFiles();
                $app->enqueueMessage($this->clearMsg, 'message');
                $app->redirect('index.php?option=com_plugins&view=plugins');
            }
        }
    }

    public function onAfterDispatch() {
        $app = Factory::getApplication();
        if (!$app->isClient('administrator')) return;

        if (!extension_loaded('gd') || !function_exists('imagewebp')) {
            $app->enqueueMessage('<strong>WebP Converter:</strong> GD Library Support fehlt.', 'warning');
        }

        if ($app->input->get('option') === 'com_plugins' && !$app->input->get('task')) {
            $this->displayStats();
        }
    }

    public function onAfterRender() {
        $app = Factory::getApplication();
        if ($app->isClient('administrator') || $app->isClient('cli')) return;

        $body = $app->getBody();
        if (empty($body)) return;

        $maxWidth = (int) $this->params->get('max_width', 1280);
        $quality  = (int) $this->params->get('quality', 80);
        $debug    = (int) $this->params->get('debug_mode', 0);

        $regex = '/(src|href|data-thumb|url)\s*[=:]\s*(["\'(])([^"\'\)]+\.(jpe?g|png))\2/i';
        preg_match_all($regex, $body, $matches);

        if (!empty($matches[3])) {
            $changed = false;
            foreach ($matches[3] as $imagePath) {
                if (strpos($imagePath, $this->cacheDir) !== false) continue;

                $localRelPath = ltrim(parse_url($imagePath, PHP_URL_PATH), '/');
                $baseFolder = trim(Uri::base(true), '/');
                if ($baseFolder && strpos($localRelPath, $baseFolder . '/') === 0) {
                    $localRelPath = substr($localRelPath, strlen($baseFolder) + 1);
                }

                $fullPath = JPATH_SITE . '/' . $localRelPath;

                if (file_exists($fullPath)) {
                    $pathInsideImages = str_replace('images/', '', $localRelPath);
                    $webpRelPath  = $this->cacheDir . '/' . preg_replace('/\.(jpe?g|png)$/i', '.webp', $pathInsideImages);
                    $webpFullPath = JPATH_SITE . '/' . $webpRelPath;

                    if (!file_exists($webpFullPath)) {
                        $success = $this->processImage($fullPath, $webpFullPath, $maxWidth, $quality);
                        if (!$success && $debug) {
                            $this->logError("Fehler bei: " . $localRelPath);
                        }
                    }
                    
                    if (file_exists($webpFullPath)) {
                        $newUrl = Uri::base(true) . '/' . $webpRelPath;
                        $body = str_replace($imagePath, $newUrl, $body);
                        $changed = true;
                    }
                }
            }
            if ($changed) $app->setBody($body);
        }
    }

    private function processImage($source, $destination, $maxWidth, $quality) {
        $dir = dirname($destination);
        if (!is_dir($dir)) @mkdir($dir, 0755, true);
        
        $info = @getimagesize($source);
        if (!$info) return false;

        $img = ($info['mime'] == 'image/jpeg') ? @imagecreatefromjpeg($source) : @imagecreatefrompng($source);
        if (!$img) return false;

        // Rotation in memory only (original file is untouched)
        if ($info['mime'] == 'image/jpeg' && function_exists('exif_read_data')) {
            $exif = @exif_read_data($source);
            if (isset($exif['Orientation'])) {
                switch ($exif['Orientation']) {
                    case 3: $img = imagerotate($img, 180, 0); break;
                    case 6: $img = imagerotate($img, -90, 0); break;
                    case 8: $img = imagerotate($img, 90, 0); break;
                }
            }
        }

        if ($info['mime'] == 'image/png') {
            imagepalettetotruecolor($img);
            imagealphablending($img, true);
            imagesavealpha($img, true);
        }

        $w = imagesx($img);
        if ($w > $maxWidth) {
            $h = imagesy($img);
            $newH = floor($h * ($maxWidth / $w));
            $tmp = imagecreatetruecolor($maxWidth, $newH);
            imagealphablending($tmp, false);
            imagesavealpha($tmp, true);
            imagecopyresampled($tmp, $img, 0, 0, 0, 0, $maxWidth, $newH, $w, $h);
            $success = @imagewebp($tmp, $destination, $quality);
            imagedestroy($tmp);
        } else {
            $success = @imagewebp($img, $destination, $quality);
        }
        imagedestroy($img);
        return $success;
    }

    private function clearWebpFiles() {
        $basePath = str_replace('\\', '/', JPATH_SITE);
        $imagesPath = $basePath . '/images';
        $cachePath  = $basePath . '/' . $this->cacheDir;

        if (!is_dir($imagesPath)) return;

        // SKIP_DOTS Fix
        $directory = new RecursiveDirectoryIterator($imagesPath, RecursiveDirectoryIterator::SKIP_DOTS);
        $it = new RecursiveIteratorIterator($directory, RecursiveIteratorIterator::CHILD_FIRST);

        $fileCount = 0;
        foreach ($it as $file) {
            $filePath = str_replace('\\', '/', $file->getRealPath());
            if ($file->isFile() && strtolower($file->getExtension()) === 'webp') {
                if (strpos($filePath, $cachePath) !== false) {
                    if (@unlink($filePath)) $fileCount++;
                } else {
                    $pathWithoutExt = substr($filePath, 0, -5);
                    if (file_exists($pathWithoutExt . '.jpg') || file_exists($pathWithoutExt . '.png') || 
                        file_exists($pathWithoutExt . '.jpeg') || file_exists($pathWithoutExt . '.JPG')) {
                        if (@unlink($filePath)) $fileCount++;
                    }
                }
            }
        }
        
        if (is_dir($cachePath)) {
            $this->rmDirRecursive($cachePath);
        }

        $this->clearMsg = "Deep Clean abgeschlossen: $fileCount WebP-Dateien entfernt.";
    }

    private function rmDirRecursive($dir) {
        if (!is_dir($dir)) return;
        $files = array_diff(scandir($dir), array('.', '..'));
        foreach ($files as $file) {
            (is_dir("$dir/$file")) ? $this->rmDirRecursive("$dir/$file") : @unlink("$dir/$file");
        }
        return @rmdir($dir);
    }

    private function displayStats() {
        $path = JPATH_SITE . '/' . $this->cacheDir;
        if (!is_dir($path)) return;

        // Auch hier: SKIP_DOTS Fix
        $directory = new RecursiveDirectoryIterator($path, RecursiveDirectoryIterator::SKIP_DOTS);
        $files = new RecursiveIteratorIterator($directory);
        
        $count = iterator_count($files);
        if ($count > 0) {
            Factory::getApplication()->enqueueMessage("📊 <strong>WebP Optimizer:</strong> $count Bilder im Cache.", 'info');
        }
    }

    private function logError($message) {
        $logFile = JPATH_ADMINISTRATOR . '/logs/plg_webp_converter.php';
        if (!file_exists($logFile)) {
            @file_put_contents($logFile, "<?php die('Restricted access'); ?>\n", FILE_APPEND);
        }
        $date = Factory::getDate()->format('Y-m-d H:i:s');
        @file_put_contents($logFile, "$date - $message\n", FILE_APPEND);
    }
}
