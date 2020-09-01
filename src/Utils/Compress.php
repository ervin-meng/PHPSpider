<?php
namespace PHPSpider\Utils;

class Compress
{
    static public function toZip($files,$zipfile)
    {
        $zip = new ZipArchive();

        if($zip->open($zipfile,ZipArchive::OVERWRITE)=== TRUE)
        {
            $fiels = is_array($fiels)?$fiels:array($fiels);

            foreach($fiels as $file)
            {
                if(is_file($file))
                {
                    $zip->addFile($file,basename($file));
                }
            }

            $zip->close();

            return true;
        }

        return false;
    }
}