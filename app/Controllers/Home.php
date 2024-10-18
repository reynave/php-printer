<?php

namespace App\Controllers; 
use CodeIgniter\Model;   

use Mike42\Escpos\Printer;
use Mike42\Escpos\PrintConnectors\WindowsPrintConnector;
use Mike42\Escpos\CapabilityProfile;


class Home extends BaseController
{
    public function index()
    {
  
        $data = array(
            "error" => false,
            "date" => date("Y-m-d H:i:s"),

        );
        return $this->response->setJSON($data);
    }

    function test()
    {
        $post = $this->request->getVar();
        $data = array(
            "error" => true,
            "post" => $post,
        );
        if($post['name']){ 
            $printer = $post['name']; 
 
            $profile = CapabilityProfile::load("simple");
            $connector = new WindowsPrintConnector($printer);
            $printer = new Printer($connector, $profile);
            $printer->text("\n\n\n" . $post['text'] . "\n\n\n");
            $printer->cut();
            $printer->close();
             
            $data = array(
                "printer" => $printer,
                "post" => $post,
            );
        }
        return $this->response->setJSON($data);
    }
    
    function cashDrawer()
    {
        //$post = json_decode(file_get_contents('php://input'), true);
         $post = $this->request->getVar();
        
        $data = array(
            "error" => true,
            "post" => $post,
        );
       
        if($post['name']){ 
            $printer = $post['name']; 

            $profile = CapabilityProfile::load("simple");
            $connector = new WindowsPrintConnector($printer);
            $printer = new Printer($connector, $profile);
            $printer->pulse();
            $printer->close();  

            $data = array(
                "error" => false,
                "printer" => $printer,
                "post" => $post,
                "action" => "Cash Drawer"
            );
        }
        return $this->response->setJSON($data);
    }

}
