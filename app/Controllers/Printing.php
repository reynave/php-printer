<?php

namespace App\Controllers;

use CodeIgniter\Model;
use Unsplash\HttpClient;

use Mike42\Escpos\Printer;
use Mike42\Escpos\PrintConnectors\WindowsPrintConnector;
use Mike42\Escpos\CapabilityProfile;

class Printing extends BaseController
{
    public function index()
    {
        $q1 = "SELECT t.*, u.name  FROM cso1_transaction as t
        join cso1_user as u on u.id = t.cashierId
        where t.presence  = 1 and t.locked = 1 order by t.inputDate DESC";
        $items = $this->db->query($q1)->getResultArray();

        $data = array(
            "error" => false,
            "items" => $items,
        );
        return $this->response->setJSON($data);
    }

    function test()
    {
        $post = json_decode(file_get_contents('php://input'), true);
        $data = array(
            "error" => true,
            "post" => $post,
        );
        if ($post) {

            $printer = $post['printerName'];


            if ($printer != "") {
                $profile = CapabilityProfile::load("simple");
                $connector = new WindowsPrintConnector($printer);
                $printer = new Printer($connector, $profile);
                $printer->text("\n\n\n" . $post['outputPrint'] . "\n\n\n");
                $printer->cut();
                $printer->close();
            }

            $data = array(
                "printer" => $printer,
                "post" => $post,
            );
        }
        return $this->response->setJSON($data);
    }


    function fnSavePrinterName()
    {
        $post = json_decode(file_get_contents('php://input'), true);
        $data = array(
            "error" => true,
            "post" => $post,
        );
        if ($post) {

            $printer = $post['printerName'];

            $this->db->table("cso1_account")->update([
                "value" => $printer,
            ], " id = 400");

            $data = array(
                "printer" => $printer,
                "post" => $post,
            );
        }
        return $this->response->setJSON($data);
    }





    function detail()
    {
        $data = [];
       
        if ($this->request->getVar()['id'] && $this->request->getVar()['id'] != "undefined") {
            $id = str_replace(["'", '"', "-"], "", $this->request->getVar()['id']);
            $isId = model("Core")->select("endDate", "cso1_transaction", "id='" . $id . "'");

            $this->db->table("cso1_transaction_detail")->update([
                "note" => "",
            ]," note is null AND transactionId = '$id' ");

            
            
            $items = model("Core")->sql("SELECT t1.*, i.description, i.shortDesc, i.id as 'itemId'
                FROM (
                    SELECT count(td.itemId) as qty, td.itemId, sum(td.price ) as 'totalPrice', td.originPrice, sum(td.isPriceEdit) as 'totalPriceEdit',
                    td.price, td.barcode, td.memberDiscountAmount, td.validationNota,
                    sum(td.isSpecialPrice) as 'isSpecialPrice', sum(td.discount) as 'totalDiscount', td.note, td.promotionId
                    from cso1_transaction_detail as td
                    where td.presence = 1 and td.void = 0 and td.transactionId = '$id' and td.isFreeItem = 0
                    group by td.itemId, td.price, td.originPrice, td.note , td.barcode, td.promotionId, td.memberDiscountAmount, td.validationNota
                ) as t1
                JOIN cso1_item as i on i.id = t1.itemId
                ORDER BY i.description ASC
            ");
            $i = 0;
            foreach ($items as $row) {
                $items[$i]['promotionDescription'] = model("Core")->select("description", "cso1_promotion", "id = '" . $row['promotionId'] . "'  ");
                $items[$i]['id'] = (int) model("Core")->select("id", "cso1_transaction_detail", "transactionId = '$id'  and itemId = '" . $row['itemId'] . "' order by inputDate DESC  ");

                $i++;
            }
            model("Promo")->orderByID($items);



            $data = array(
                "get" => $this->request->getVar(),
                "id" => $id,
                "printable" => $isId ? true : false,
                "date" => model("Core")->select("endDate", "cso1_transaction", "id='" . $id . "'"),
                "detail" => $isId ? model("Core")->sql("SELECT t.*, p.label as 'paymentName' 
                from cso1_transaction  as t
                left join cso1_payment_type as p on p.id = t.paymentTypeId
                where t.id= '" . $id . "' ")[0] : [],

                "items" => $items,
                "freeItem" => model("Core")->sql("SELECT  
                    f.freeItemId as 'barcode', f.freeItemId as 'itemId', k.promotionFreeId, COUNT(k.promotionFreeId) AS 'qty',
                    (COUNT(k.promotionFreeId) / f.qty) * f.freeQty AS 'getFreeItem',
                    i.description, i.shortDesc 
                FROM cso1_transaction_detail AS k 
                LEFT JOIN cso1_promotion_free AS f ON f.id = k.promotionFreeId
                LEFT JOIN cso1_item AS i ON i.id = f.freeItemId 
                WHERE k.transactionId = '$id'  AND k.void = 0 and k.presence = 1 AND f.freeItemId != ''  
                GROUP BY k.promotionFreeId
                "),

                "summary" => array(
                    "nonBkp" => (int) model("Core")->select("nonBkp", "cso1_transaction", "id='$id'"),
                    "bkp" => (int) model("Core")->select("bkp", "cso1_transaction", "id='$id'"),
                    "discount" => (int) model("Core")->select("discount", "cso1_transaction", "id='$id'"),
                    "dpp" => (int) model("Core")->select("dpp", "cso1_transaction", "id='$id'"),
                    "discountMember" => (int) model("Core")->select("discountMember", "cso1_transaction", "id='$id'"),
                    "ppn" => (int) model("Core")->select("ppn", "cso1_transaction", "id='$id'"),
                    "total" => (int) model("Core")->select("total", "cso1_transaction", "id='$id'"),
                    "voucher" => (int) model("Core")->select("voucher", "cso1_transaction", "id='$id'"),
                    "final" => (int) model("Core")->select("finalPrice", "cso1_transaction", "id='$id'"),
                ),
                "paymentMethod" => model("Core")->sql("SELECT 
                    tp.id, tp.amount, tp.paymentTypeId, p.label, tp.input_date, tp.voucherNumber, tp.paymentNameId
                    FROM cso1_transaction_payment AS tp 
                    LEFT JOIN cso1_payment_type AS p ON p.id = tp.paymentTypeId
                    WHERE tp.transactionId = '$id' AND tp.presence = 1"),

                "balance" => model("Core")->sql("SELECT SUM(cashIn) AS 'caseIn', SUM(cashOut)*-1 AS 'caseOut'
                    FROM cso2_balance 
                    WHERE transactionId = '$id'
                    GROUP BY transactionId 
                "),

                "template" => array(
                    "companyName" => model("Core")->select("value", "cso1_account", "name='companyName'"),
                    "companyAddress" => model("Core")->select("value", "cso1_account", "name='companyAddress'"),
                    "companyPhone" => 'Telp : ' . model("Core")->select("value", "cso1_account", "name='companyPhone'"),
                    "footer" => model("Core")->select("value", "cso1_account", "id='1007'"),
                    "brandId" => model("Core")->select("value", "cso1_account", "id='22'"),
                    "outletId" => model("Core")->select("value", "cso1_account", "id='21'"),
                ),
                "copy" => (int) model("Core")->sql(" select count(id) as 'copy' from cso1_transaction_printlog where transactionId ='$id'")[0]['copy'],
                "isCash" => (int) model("Core")->sql(" SELECT count(id) as 'cash' from cso1_transaction_payment WHERE paymentTypeId = 'CASH' and  transactionId ='$id'"),

            );


            $i = 0;

            foreach ($data['paymentMethod'] as $rec) {
                if ($rec['paymentTypeId'] == 'VOUCHER') {
                    $voucherNumber = $rec['voucherNumber'];
                    $data['paymentMethod'][$i]['label'] = $rec['label'] . ' ' . $voucherNumber;
                } else {
                    $data['paymentMethod'][$i]['label'] = $rec['label'] . ' ' . model("Core")->select("name", "cso1_payment_name", "id = '" . $rec['paymentNameId'] . "' ");
                }
                $i++;
            }
            if (isset($data['detail']['memberId'])) {
                $data['detail']['member'] = strtoupper(model("Core")->select("name", "cso2_member", "id = '" . $data['detail']['memberId'] . "' "));

            }

            $data['promo_fixed'] = model("Promo")->promo_fixed($data['summary']['total']);
        }
        return $this->response->setJSON($data);
    }


    function openCashDrawer()
    {
        $post = json_decode(file_get_contents('php://input'), true);
        $data = array(
            "error" => true,
            "post" => $post,
        );
        if ($post) {
            $this->db->table("cso1_transaction")->update([
                "cashDrawer" => 1,
            ], " id = '" . $post['id'] . "'");
            $printer = model("Core")->printer();
            if ($printer != "") {

                $profile = CapabilityProfile::load("simple");
                $connector = new WindowsPrintConnector($printer);
                $printer = new Printer($connector, $profile);
                if ($post['cashDrawer'] == 0) {
                    $printer->pulse();
                }
                $printer->close();
            }




            $data = array(
                "note" => 'success',
                "printer" => $printer,
                "post" => $post,
                "action" => "Print, Cut and Cash Drawer"
            );
        }
        return $this->response->setJSON($data);
    }
    function fnPrinting()
    {
        $post = json_decode(file_get_contents('php://input'), true);
        $data = array(
            "error" => true,
            "post" => $post,
        );
        if ($post) {

            $printer = model("Core")->printer();

            $this->db->table("cso1_transaction")->update([
                "printing" => 1,
            ], " id = '" . $post['id'] . "'");


            if ($printer != "") {

                $profile = CapabilityProfile::load("simple");
                $connector = new WindowsPrintConnector($printer);
                $printer = new Printer($connector, $profile);

                $printer->text($post['outputPrint']);
                $printer->cut();
                $printer->close();
            }

            $data = array(
                "note" => 'success',
                "printer" => $printer,
                "post" => $post,
                "action" => "Print and Cut"
            );
        }
        return $this->response->setJSON($data);
    }


    function fnPrintingNota()
    {
        $post = json_decode(file_get_contents('php://input'), true);
        $data = array(
            "error" => true,
            "post" => $post,
        );
        if ($post) {

            $printer = model("Core")->printer();


            if ($printer != "") {

                $profile = CapabilityProfile::load("simple");
                $connector = new WindowsPrintConnector($printer);
                $printer = new Printer($connector, $profile);

                $printer->text($post['outputPrint']);
                $printer->cut();
                $printer->close();
            }

            $data = array(
                "note" => 'success',
                "printer" => $printer,
                "post" => $post,
                "action" => "Print and Cut"
            );
        }
        return $this->response->setJSON($data);
    }


    function copyPrinting()
    {
        $data = [];
        $id = str_replace(["'", '"', "-"], "", $this->request->getVar()['id']);
        if ($id) {
            $isId = model("Core")->select("endDate", "cso1_transaction", "id='" . $id . "'");
            if ($isId) {
                $this->db->table("cso1_transaction_printlog")->insert([
                    "transactionId" => $id,
                    "inputDate" => time(),
                    "input_date" => date("Y-m-d H:i:s"),
                ]);

            }
            $data = array(
                "copy" => (int) model("Core")->sql(" select count(id) as 'copy' from cso1_transaction_printlog where transactionId ='$id'")[0]['copy'],

            );
        }

        return $this->response->setJSON($data);
    }
}