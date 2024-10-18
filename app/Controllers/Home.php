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
        $q1 = "SELECT *  FROM auto_number limit 1";
        $items = $this->db->query($q1)->getResultArray();

        $data = array(
            "error" => false,
            "con" => 200,
            "items" => $items,

        );
        return $this->response->setJSON($data);
    }

 
    public function start()
    {
      
        $data = array(
            "error" => false,
            "cashIn" => (int)model("Core")->select("cashIn","cso2_balance"," close = 0 AND transactionId = '_S1' "), 
            
        );
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
