<?php

namespace App\Models;

use CodeIgniter\Model;
use CodeIgniter\CodeIgniter;

class Core extends Model
{
    protected $id = null;
    protected $db = null;
    protected $request = null;

    function __construct()
    {
        $this->request = \Config\Services::request();
        $this->db = \Config\Database::connect();
    }


    function select($field = "", $table = "", $where = " 1 ")
    {
        $data = null;
        if ($field != "") {
            $query = $this->db->query("SELECT $field  FROM $table WHERE $where LIMIT 1");
            if ($query->getRowArray()) {
                $row = $query->getRowArray();
                $data = $row[$field];
            }
        } else {
            $data = null;
        }
        return $data;
    }
    function header()
    {
        if (service('request')->getHeaderLine('Token')) {
            $jwtObj = explode('.', service('request')->getHeaderLine('Token'));
            $user = base64_decode($jwtObj[1]);
            $data = json_decode($user, true);
        } else {
            $data = false;
        }

        return $data;
    }
    function accountId()
    {
        return self::header() != false ? self::header()['account']['id'] : "";
    }


    function number($name = "")
    {
        if ($name) {
            $number = self::select('runningNumber', 'auto_number', "name = '" . $name . "'") + 1;
            $prefix = self::select('prefix', 'auto_number', "name = '" . $name . "'");
            if ($prefix == '$year') {
                $prefix = date("Y");
            }

            $this->db->table("auto_number")->update([
                "runningNumber" => $number,
                "updateDate" => time(),
            ], "name = '" . $name . "' ");

            $new_number = str_pad($number, self::select('digit', 'auto_number', "name = '" . $name . "'"), "0", STR_PAD_LEFT);

            return $prefix . $new_number;
        }
    }

    function buildTree($arr, $parentId = 0)
    {
        $tree = [];

        foreach ($arr as $item) {
            if ($item['parent_id'] == $parentId) {
                $item['children'] = self::buildTree($arr, $item['id']);
                $tree[] = $item;
            }
        }

        return $tree;
    }

    function deleteNode($parentId)
    {

        // Mengupdate nilai presence menjadi 0 pada parent yang dihapus
        $this->db->table('pages')->where('id', $parentId)->update(['presence' => 0]);

        // Mengambil semua child yang terkait dengan parent yang dihapus
        $children = $this->db->table('pages')->where('parent_id', $parentId)->get()->getResultArray();

        // Menghapus atau mengupdate presence pada setiap child secara rekursif
        foreach ($children as $child) {
            self::deleteNode($child['id']);
        }
    }

    function put($data = [], $table = "", $where = "")
    {
        $res = false;
        if ($where != "") {
            $id = self::select("id", $table, $where);
            if (!$id) {
                $res = $this->db->table($table)->insert([
                    "presence" => 1,
                    "update_date" => date("Y-m-d H:i:s"),
                    "update_by" => model("Core")->accountId(),
                    "input_date" => date("Y-m-d H:i:s"),
                    "input_by" => model("Core")->accountId(),
                ]);
                $id = self::select("id", $table, " input_by = '" . model("Core")->accountId() . "' order by input_by DESC ");
            }

            foreach ($data as $key => $value) {

                $this->db->table($table)->update([
                    $key => $value,
                    "update_date" => date("Y-m-d H:i:s"),
                    "update_by" => model("Core")->accountId(),
                ], "id = $id ");
            }
        }
        return $res;
    }

    function sql($q)
    {
        $query = $this->db->query($q);
        return $query->getResultArray();
    }

    function summary($uuid = "")
    {
        $memberId = self::select("memberId", "cso1_kiosk_uuid", "presence = 1 AND status = 1  AND kioskUuid = '" . $uuid . "'");

        $discountMember = 0;
        if ((int) $memberId > 0) {
            $discountMember = (int) self::sql("SELECT sum(discountAmount) as 'discountAmount', 
        sum(discountPercent) as 'discountPercent'
        from cso1_promotion where presence =1 and status =  1 and startDate >= " . time() . "  and endDate <= " . time())[0]['discountAmount'];
        }
        $bkp = (int) self::sql("  SELECT  sum(c.price) as 'total'
                from cso1_kiosk_cart as c
                join cso1_item as i on i.id = c.itemId
                join cso1_taxcode as x on x.id = i.itemTaxId
                where c.presence = 1 and  kioskUuid = '$uuid' and x.percentage > 0 and x.taxType = 1
            ")[0]['total'] + (int) self::sql("  SELECT  sum(c.price*(x.percentage /100) + c.price ) as 'total'
                from cso1_kiosk_cart as c
                join cso1_item as i on i.id = c.itemId
                join cso1_taxcode as x on x.id = i.itemTaxId
                where c.presence = 1 and  kioskUuid = '$uuid' and x.percentage > 0 and x.taxType = 0
            ")[0]['total'];
        $nonBkp = (int) self::sql("  SELECT   sum(c.price) as 'total'
            from cso1_kiosk_cart as c
            join cso1_item as i on i.id = c.itemId
            join cso1_taxcode as x on x.id = i.itemTaxId
            where c.presence = 1 and  kioskUuid = '$uuid' and x.percentage = 0")[0]['total'];


        $ppnExc = (int) self::sql("SELECT sum(((c.price - c.discount) * (t.percentage/100)) ) as 'tax' 
            from cso1_kiosk_cart as c
            join cso1_item as i on c.itemId = i.id
            left join cso1_taxcode as t on t.id = i.itemTaxId
            where c.presence = 1 and c.isFreeItem = 0 and c.kioskUuid = '$uuid' and t.taxType = 0 ")[0]['tax'];

        $ppnInc = (int) self::sql("SELECT sum(c.price - ((c.price - c.discount) / (t.percentage/100 + 1))) as    'ppnInc' 
            from cso1_kiosk_cart as c
            join cso1_item as i on c.itemId = i.id
            left join cso1_taxcode as t on t.id = i.itemTaxId
            where c.presence = 1 and c.isFreeItem = 0 and c.kioskUuid = '$uuid' and t.taxType = 1 ")[0]['ppnInc'];

        $summary = array(
            "total" => self::sql("SELECT sum(k.price) as 'subTotal'
                    FROM cso1_kiosk_cart as k
                    where k.presence = 1 and k.kioskUuid = '$uuid' ")[0]['subTotal'],
            "discount" => self::sql("SELECT sum(k.discount) as 'discount'
                    FROM cso1_kiosk_cart as k
                    where k.presence = 1 and k.kioskUuid = '$uuid' ")[0]['discount'],
            "memberDiscount" => $discountMember,
            "voucer" => 0,

            // Barang Kena Pajak 
            "bkp" => $bkp - ($ppnExc + $ppnInc),
            "dpp" => $bkp + $nonBkp,

            //harga sebelum ppn + (harga sebelum ppn x 0.11) = 100.000
            "ppn" => $ppnInc + $ppnExc,

            "nonBkp" => $nonBkp,
            "final" => 0,

        );

        $summary['final'] = $summary['total'] - $summary['discount'] - $summary['memberDiscount'];
    //    $summary['final'] =  $summary['final'] < 0 ? 1 :  $summary['final'];
        return $summary;
    }
    function barcode($code)
    {
        $barcode = str_split($code);
        if (count($barcode) >= 13) {  
            $digitPrefixPosition = (int) self::select("value", "cso1_account", "id=51");
            $digitItem = (int) self::select("value", "cso1_account", "id=52");
            $digitWeight = (int) self::select("value", "cso1_account", "id=53");
            $digitFloat = (int) self::select("value", "cso1_account", "id=54");

            $item = "";

            $prefix = $barcode[$digitPrefixPosition - 1];
            for ($i = 1; $i <= $digitItem; $i++) {
                $item .= $barcode[$i];
            }
            $weight = "";
            for ($i = $digitItem + 1; $i <= $digitItem + $digitWeight; $i++) {
                $weight .= $barcode[$i];
            }
            $pow = 10;
            for ($i = 1; $i < +$digitFloat; $i++) {
                $pow = 10 * $pow;
            }
            $weight = (float) $weight / $pow;

            $checkDigit = !isset($barcode[$digitPrefixPosition + $digitItem + $digitWeight]) ? 0 : $barcode[$digitPrefixPosition + $digitItem + $digitWeight];


            $array = array(
                "barcode" => $code,
                // "raw" =>    $barcode,
                "config" => array(
                    "digitPrefixPosition" => $digitPrefixPosition,
                    "digitItem" => $digitItem,
                    "digitWeight" => $digitWeight,
                    "digitFloat" => $digitFloat,
                ),
                "prefix" => $prefix,
                "itemId" => $prefix == 2 ? $item : $code,
                "weight" => $weight,
                "checkDigit" => $checkDigit,
            );
            return $array;
        } else {
            return $code;
        }

    }

    function printer(){
        return self::select("value","cso1_account","id=400");
    }
}