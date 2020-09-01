<?php
namespace PHPSpider\Utils;

use PHPExcel;
use PHPExcel_IOFactory;
use PHPExcel_Settings;
use PHPExcel_CachedObjectStorageFactory;
use PHPExcel_Style_Fill;
use PHPExcel_Style_Border;
use PHPExcel_Cell_DataType;

class Excel
{
    static public function save($excel_header,$excel_data,$excel_name,$excel_dir='')
    {
        $num2en = array('0'=>'','1'=>'A','2'=>'B','3'=>'C','4'=>'D','5'=>'E','6'=>'F','7'=>'G','8'=>'H','9'=>'I','10'=>'J','11'=>'K','12'=>'L','13'=>'M','14'=>'N','15'=>'O','16'=>'P','17'=>'Q','18'=>'R','19'=>'S','20'=>'T','21'=>'U','22'=>'V','23'=>'W','24'=>'X','25'=>'Y','26'=>'Z');

        $total_col = count($excel_header);
        $decade = floor($total_col/26);
        $bit = $total_col-$decade*26;
        $maxCol = $num2en[$decade].$num2en[$bit].'1';
        $minCol = 'A1';

        PHPExcel_Settings::setCacheStorageMethod(PHPExcel_CachedObjectStorageFactory::cache_in_memory_gzip,array());

        $objPHPExcel = new PHPExcel;
        $objPHPExcel->getProperties()->setCreator("Hui10");
        $objPHPExcel->setActiveSheetIndex(0);
        $objPHPExcel->getActiveSheet()->getStyle( $minCol.':'.$maxCol)->getFill()->setFillType(PHPExcel_Style_Fill::FILL_SOLID);
        $objPHPExcel->getActiveSheet()->getStyle( $minCol.':'.$maxCol)->getFill()->getStartColor()->setARGB('FFFFC000');
        $borderstyle = array('borders'=>array('outline' => array ('style' => PHPExcel_Style_Border::BORDER_THIN,'color' => array ('argb' => 'FF000000'))));
        $objPHPExcel->getActiveSheet()->getStyle( $minCol.':'.$maxCol)->applyFromArray($borderstyle);

        $i=1;

        foreach($excel_header as $v)
        {
            if($i>26)
            {
                $parent = floor($i/26);
                $child = $i-$parent*26;
                $k = $num2en[$parent].$num2en[$child].'1';
            }
            else
            {
                $k = $num2en[$i].'1';
            }

            $objPHPExcel->getActiveSheet()->setCellValue($k,$v);
            $i++;
        }

        $j=2;

        foreach($excel_data as $k=>$value)
        {
            $i=1;

            foreach($excel_header as $key => $v)
            {
                if($i>26)
                {
                    $parent = floor($i/26);
                    $child = $i-$parent*26;
                    $k = $num2en[$parent].$num2en[$child].$j;
                }
                else
                {
                    $k = $num2en[$i].$j;
                }
                $objPHPExcel->getActiveSheet()->setCellValueExplicit($k,$value[$key],  is_float($value[$key])?PHPExcel_Cell_DataType::TYPE_NUMERIC:PHPExcel_Cell_DataType::TYPE_STRING);
                $i++;
            }
            $j++;
        }

        /**
        $objPHPExcel->getActiveSheet()->getStyle('A'.$j)->getAlignment()->setHorizontal(PHPExcel_Style_Alignment::HORIZONTAL_RIGHT);
        $objPHPExcel->getActiveSheet()->mergeCells('A'.$j.':'.$num2en[$decade].$num2en[$bit].($j+1));
        **/

        ob_clean();

        if(!empty($excel_dir))
        {
            $objWriter = PHPExcel_IOFactory::createWriter($objPHPExcel, 'Excel5');
            $objWriter->save($excel_dir.'/'.$excel_name.'.xls');
            $objPHPExcel->disconnectWorksheets();
        }
        else
        {
            header('Content-Type: application/vnd.ms-excel');
            header('Content-Disposition: attachment;filename="'.$excel_name.'.xls"');
            header('Cache-Control: max-age=0');
            header('Cache-Control: max-age=1');
            header ('Last-Modified: '.gmdate('D, d M Y H:i:s').' GMT');
            header ('Cache-Control: cache, must-revalidate');
            header ('Pragma: public');
            $objWriter = PHPExcel_IOFactory::createWriter($objPHPExcel, 'Excel5');
            $objWriter->save('php://output');
        }
    }
}