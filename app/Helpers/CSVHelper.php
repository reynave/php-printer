<?php namespace App\Helpers;

class CSVHelper {

    public static function arrayToCsv($data, $filename, $dateFolder = "temp"  ) {
       // $dateFolder = date('Y-m-d'); // Mendapatkan tanggal hari ini dalam format 'YYYY-MM-DD'
        $folderPath = FCPATH . './../../settlement/' . $dateFolder . '/';

        // Membuat folder jika belum ada
        if (!is_dir($folderPath)) {
            mkdir($folderPath, 0777, true);
        }

        $filePath = $folderPath . $filename . '.csv';

        $file = fopen($filePath, 'w');
        
        if (!$file) {
            return false;
        }

        // Menulis header (kolom) CSV 
        fputcsv($file, array_keys($data[0]),';');

        // Menulis baris data
        foreach ($data as $row) {
            fputcsv($file, $row,';');
        }

        fclose($file);

        return $filePath;
    }
}
