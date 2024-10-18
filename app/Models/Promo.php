<?php

namespace App\Models;

use CodeIgniter\Model;
use CodeIgniter\CodeIgniter;
use Voucher;

class Promo extends Model
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
    function getId($itemId = "", $qty = 0)
    {
        $today = date('D', time());
        $q = "SELECT  i.promotionId, i.id AS 'promotionItemId', i.qtyFrom, i.qtyTo, p.typeOfPromotion,
        FROM_UNIXTIME(p.startDate) AS 'startDate', FROM_UNIXTIME(p.endDate) AS 'endDate', NOW() as 'nowDate',  p.$today as '$today' 
        FROM cso1_promotion_item AS i
        LEFT JOIN cso1_promotion AS p ON p.id = i.promotionId
        WHERE i.itemId = '$itemId' AND  p.typeOfPromotion = 1 
        AND (p.startDate < unix_timestamp(now()) AND unix_timestamp(NOW()) <  p.endDate)
 
        AND ( $qty > i.qtyFrom and $qty <= i.qtyTo )
        AND p.$today = 1 AND p.`status` = 1 AND p.presence = 1 AND i.presence = 1 AND i.`status` = 1";
        $items = $this->db->query($q)->getResultArray();


        $data = array(
            "error" => count($items) > 0 ? false : true,
            "promo" => $items,
            "q" => $q,
        );

        return $data;
    }

    // ver 1 DELETE
    function getPromo($itemId, $qty = 0)
    {
        $promo = self::getId($itemId, $qty);
        $i = 1;
        if ($promo['error'] == false) {

            $promotionItemId = $promo['promo'][0]['promotionItemId'];
            $typeOfPromotion = $promo['promo'][0]['typeOfPromotion'];

            $isSpecialPrice = (int) self::select("specialPrice", "cso1_promotion_item", "id=$promotionItemId") > 0 ? 1 : 0;
            if ($isSpecialPrice == 1) {
                $newPrice = (int) self::select("specialPrice", "cso1_promotion_item", "id=$promotionItemId");
            } else {
                $discountPrice = self::select("discountPrice", "cso1_promotion_item", "id=$promotionItemId");

                $discx = [
                    "disc1" => self::select("disc1", "cso1_promotion_item", "id=$promotionItemId"),
                    "disc2" => self::select("disc2", "cso1_promotion_item", "id=$promotionItemId"),
                    "disc3" => self::select("disc3", "cso1_promotion_item", "id=$promotionItemId"),
                ];

                $price = self::select("price$i", "cso1_item", "id=$itemId");


                $disc1 = $discx['disc1'] / 100;
                $disc2 = $discx['disc2'] / 100;
                $disc3 = $discx['disc3'] / 100;

                $discLevel1 = $price * $disc1;
                $discLevel2 = ($price - $discLevel1) * $disc2;
                $discLevel3 = ($price - $discLevel1 - $discLevel2) * $disc3;

                $discLevel = $discLevel1 + $discLevel2 + $discLevel3;


                $newPrice = $price - ($discountPrice + $discLevel);

            }
            $data = array(
                "itemId" => $itemId,
                "typeOfPromotion" => $typeOfPromotion,
                "promotionId" => $promo['promo'][0]['promotionId'],
                "promotionItemId" => $promotionItemId,
                "newPrice" => (int) $newPrice,
                "isSpecialPrice" => $isSpecialPrice > 0 ? 1 : 0,
                "discount" => $isSpecialPrice == 1 ? 0 : $discountPrice + $discLevel,
                "promoDetail" => $promo,
            );


        } else {
            $data = array(
                "itemid" => null,
                "typeOfPromotion" => 0,
                "promotionId" => 0,
                "promotionItemId" => 0,
                "newPrice" => 0,
                "isSpecialPrice" => 0,
                "discount" => 0,
            );
        }
        return $data;
    }

    // ver 2
    function promotion_item($itemId, $qty = 0)
    {
        $today = date('D', time());
        $q2 = "SELECT * FROM cso1_promotion_item 
        WHERE 
            itemId= '$itemId' AND 
            qtyFrom <= $qty AND qtyTo >= $qty
        ";


        $q = "SELECT i.* , p.startDate, p.endDate, p.$today, unix_timestamp(now()) AS 'time'
        FROM cso1_promotion_item  AS i
        LEFT JOIN cso1_promotion AS p ON p.id = i.promotionId
        WHERE 
            i.itemId= '$itemId' AND 
            i.qtyFrom <=  $qty AND i.qtyTo >= $qty AND 
            p.startDate <= unix_timestamp(NOW()) AND p.endDate >= unix_timestamp(NOW()) AND 
            p.$today = 1 
        ";
        $promotionItemId = 0;
        $promotionId = 0;
        $isSpecialPrice = 0;
        $newPrice = model("Core")->select("price1", "cso1_item", "id = '$itemId' ");
        if (count($this->db->query($q)->getResultArray()) > 0) {
            $promo = $this->db->query($q)->getResultArray()[0];
            $promotionItemId = $promo['id'];
            $promotionId = $promo['promotionId'];
            $isSpecialPrice = (int) self::select("specialPrice", "cso1_promotion_item", "id=$promotionItemId") > 0 ? 1 : 0;
            if ($isSpecialPrice == 1) {
                $newPrice = (int) self::select("specialPrice", "cso1_promotion_item", "id=$promotionItemId");
            } else {
                $discountPrice = self::select("discountPrice", "cso1_promotion_item", "id=$promotionItemId");

                $discx = [
                    "disc1" => self::select("disc1", "cso1_promotion_item", "id=$promotionItemId"),
                    "disc2" => self::select("disc2", "cso1_promotion_item", "id=$promotionItemId"),
                    "disc3" => self::select("disc3", "cso1_promotion_item", "id=$promotionItemId"),
                ];

                $price = self::select("price1", "cso1_item", "id=$itemId");


                $disc1 = $discx['disc1'] / 100;
                $disc2 = $discx['disc2'] / 100;
                $disc3 = $discx['disc3'] / 100;

                $discLevel1 = $price * $disc1;
                $discLevel2 = ($price - $discLevel1) * $disc2;
                $discLevel3 = ($price - $discLevel1 - $discLevel2) * $disc3;

                $discLevel = $discLevel1 + $discLevel2 + $discLevel3;

                $newPrice = $price - ($discountPrice + $discLevel);

            }
        }

        $resh = array(
            "price" => $newPrice,
            "isSpecialPrice" => $isSpecialPrice,
            "discount" => model("Core")->select("price1", "cso1_item", "id = '$itemId' ") - $newPrice,
            "promotionItemId" => $promotionItemId,
            "promotionId" => $promotionId,

        );
        return $resh;

    }
    function promotion_discount($itemId = "")
    {
        $today = date('D', time());

        $q = "SELECT *
        FROM  
            cso1_promotion_discount 
        WHERE 
            STATUS = 1 AND presence = 1 AND 
            itemId = '$itemId'
        ";
        $resh = false;
        if (count($this->db->query($q)->getResultArray()) > 0) {
            $resh = $this->db->query($q)->getResultArray()[0];
        }
        return $resh;
    }


    function orderByID(&$array)
    {
        $length = count($array);

        for ($i = 0; $i < $length - 1; $i++) {
            for ($j = 0; $j < $length - $i - 1; $j++) {
                if ($array[$j]['id'] > $array[$j + 1]['id']) {
                    // Tukar posisi elemen jika id lebih besar
                    $temp = $array[$j];
                    $array[$j] = $array[$j + 1];
                    $array[$j + 1] = $temp;
                }
            }
        }
    }

    function promo_fixed($total = 0, $step = "")
    {


        $data = array();
        $id = 1;
        $data[] = [
            "name" => self::select("name", "cso1_promo_fixed", " id = $id"),
            "detail" => self::freeParking($total),
        ];

        $id = 10;
        $data[] = [
            "name" => self::select("name", "cso1_promo_fixed", " id = $id"),
            "detail" => self::luckyDip($total),
        ];

        $id = 20;
        $data[] = [
            "name" => self::select("name", "cso1_promo_fixed", " id = $id"),
            "detail" => self::voucher($total),
        ];

        $id = 21;
        $data[] = [
            "name" => self::select("name", "cso1_promo_fixed", " id = $id"),
            "detail" => self::voucherDiscount($total),
        ];

        for ($i = 100; $i <= 103; $i++) {
            $id = $i;
            $data[] = [
                "name" => self::select("name", "cso1_promo_fixed", " id = $id"),
                "detail" => self::promoFixed($total, $id),
            ];
        }


        return $data;
    }

    function freeParking($total = 0)
    {
        $id = 1;
        $ifAmountNearTarget = self::select("ifAmountNearTarget", "cso1_promo_fixed", "status =1 AND id = $id  AND   expDate > now()");
        $target = (int) self::select("targetAmount", "cso1_promo_fixed", "status =1 AND id = $id  AND   expDate > now()");

        $data = array(
            "description" => self::select("concat(icon,description)", "cso1_promo_fixed", "status =1 AND id = $id  AND   expDate > now() AND 
            $total >= targetAmount"),
            "shortDesc" => self::select("shortDesc", "cso1_promo_fixed", "status =1 AND id = $id  AND   expDate > now() AND 
            $total >= targetAmount"),

            "target" => $target,
            "reminder" => '',
        );
        $status = self::select("status", "cso1_promo_fixed", "status = 1  AND id = $id  AND   expDate > now()");
        if ($status == 1 && $target > 0) {
            if ((($total / $target) * 100) > (float) $ifAmountNearTarget && $data['description'] == "") {
                $data['reminder'] = "REMINDER " . self::select("description", "cso1_promo_fixed", "status =1 AND id = $id ");
            }
        }


        return $data;
    }

    function promoFixed($total = 0, $id = 100)
    {

        $ifAmountNearTarget = self::select("ifAmountNearTarget", "cso1_promo_fixed", "status =1 AND id = $id  AND   expDate > now()");
        $target = (int) self::select("targetAmount", "cso1_promo_fixed", "status =1 AND id = $id  AND   expDate > now()");

        $data = array(
            "description" => self::select("concat(icon,description)", "cso1_promo_fixed", "status =1 AND id = $id  AND   expDate > now() AND 
            $total >= targetAmount"),
            "shortDesc" => self::select("shortDesc", "cso1_promo_fixed", "status =1 AND id = $id  AND   expDate > now() AND 
            $total >= targetAmount"),
            "target" => $target,
            "reminder" => '',
        );
        $status = self::select("status", "cso1_promo_fixed", "status = 1  AND id = $id  AND   expDate > now()");
        if ($status == 1 && $target > 0) {
            if ((($total / $target) * 100) > (float) $ifAmountNearTarget && $data['description'] == "") {
                $data['reminder'] = "REMINDER " . self::select("description", "cso1_promo_fixed", "status =1 AND id = $id ");
            }
        }


        return $data;
    }



    function voucher($total = 0)
    {
        $id = 20;
        $target = (int) self::select("targetAmount", "cso1_promo_fixed", "status =1 AND id = $id  AND   expDate > now()");
        $ifAmountNearTarget = self::select("ifAmountNearTarget", "cso1_promo_fixed", "status =1 AND id = $id  AND   expDate > now()");

        $data = [
            "description" => '',
            "shortDesc" => '',
            "target" => $target,
            "reminder" => '',
        ];
        $status = self::select("status", "cso1_promo_fixed", "status = 1  AND id = $id  AND   expDate > now()");
        if ($status == 1 && $target > 0) {
            $isMultiple = self::select("isMultiple", "cso1_promo_fixed", "status = 1  AND id = $id  AND   expDate > now()");
            $n = intval($total / self::select("targetAmount", "cso1_promo_fixed", "status = 1  AND id = $id  AND   expDate > now()"));

            if ($n >= 1) {
                if ($isMultiple != 1) {
                    $n = 1;
                }
                $voucher = (int) self::select("voucherAmount", "cso1_promo_fixed", " id = $id ");
                $totalVoucer = $n * $voucher;
                if ((($total / $target) * 100) > (float) $ifAmountNearTarget && $data['description'] == "") {
                    $data['reminder'] = self::select("description", "cso1_promo_fixed", "status =1 AND id = $id ");
                }
                $data = [
                    "description" => self::select("concat(icon,description)", "cso1_promo_fixed", "status = 1 AND id = $id  AND   expDate > now() ") . "  " . number_format($totalVoucer),
                    "shortDesc" => self::select("shortDesc", "cso1_promo_fixed", "status = 1 AND id = $id  AND   expDate > now() ") . "  " . number_format($totalVoucer),
                    "target" => $target,
                    "reminder" => '',
                ];

            }
            if ((($total / $target) * 100) > (float) $ifAmountNearTarget && $data['description'] == "") {
                $data['reminder'] = "REMINDER " . self::select("description", "cso1_promo_fixed", "status =1 AND id = $id ");
            }

        }



        return $data;
    }


    function voucherDiscount($total = 0)
    {
        $id = 21;
        $discount = (float) self::select("voucherAmount", "cso1_promo_fixed", "status =1 AND id = $id  AND   expDate > now() AND 
        $total >= targetAmount") * 100;
        $ifAmountNearTarget = self::select("ifAmountNearTarget", "cso1_promo_fixed", "status =1 AND id = $id  AND   expDate > now()");
        $target = (int) self::select("targetAmount", "cso1_promo_fixed", "status =1 AND id = $id  AND   expDate > now()");


        $data = [
            "description" => $discount > 0 ? self::select("concat(icon,description)", "cso1_promo_fixed", "status =1 AND id = $id  AND   expDate > now() AND 
            $total >= targetAmount") . ' ' . $discount . ' %' : '',
            "shortDesc" => $discount > 0 ? self::select("shortDesc", "cso1_promo_fixed", "status =1 AND id = $id  AND   expDate > now() AND 
            $total >= targetAmount") . ' ' . $discount . ' %' : '',
            "target" => self::select("targetAmount", "cso1_promo_fixed", "status =1 AND id = $id  AND   expDate > now() AND $total >= targetAmount"),

        ];
        $status = self::select("status", "cso1_promo_fixed", "status = 1  AND id = $id  AND   expDate > now()");
        if ($status == 1 && $target > 0) {
            if ((($total / $target) * 100) > (float) $ifAmountNearTarget && $data['description'] == "") {
                $data['reminder'] = "REMINDER " . self::select("description", "cso1_promo_fixed", "status =1 AND id = $id ");
            }
        }
        return $data;
    }

    function luckyDip($total = 0)
    {
        $id = 10;
        $target = (int) self::select("targetAmount", "cso1_promo_fixed", "status =1 AND id = $id  AND   expDate > now()");
        $ifAmountNearTarget = self::select("ifAmountNearTarget", "cso1_promo_fixed", "status =1 AND id = $id  AND   expDate > now()");

        $data = [
            "description" => '',
            "shortDesc" => '',
            "target" => $target,
            "reminder" => '',
        ];
        $status = self::select("status", "cso1_promo_fixed", "status = 1  AND id = $id  AND   expDate > now()");
        if ($status == 1 && $target > 0) {
            $isMultiple = self::select("isMultiple", "cso1_promo_fixed", "status = 1  AND id = $id  AND   expDate > now()");
            $n = intval($total / self::select("targetAmount", "cso1_promo_fixed", "status = 1  AND id = $id  AND   expDate > now()"));

            if ($n >= 1) {
                if ($isMultiple != 1) {
                    $n = 1;
                }
                $voucher = (int) self::select("voucherAmount", "cso1_promo_fixed", " id = $id ");
                $totalVoucer = $n * $voucher;
                if ((($total / $target) * 100) > (float) $ifAmountNearTarget && $data['description'] == "") {
                    $data['reminder'] = self::select("description", "cso1_promo_fixed", "status =1 AND id = $id ");
                }
                $data = [
                    "description" => self::select("concat(icon,description)", "cso1_promo_fixed", "status = 1 AND id = $id  AND   expDate > now() ") . "  " . number_format($totalVoucer),
                    "shortDesc" => self::select("shortDesc", "cso1_promo_fixed", "status = 1 AND id = $id  AND   expDate > now() ") . "  " . number_format($totalVoucer),
                    "target" => $target,
                    "reminder" => '',
                ];

            }
            if ((($total / $target) * 100) > (float) $ifAmountNearTarget && $data['description'] == "") {
                $data['reminder'] = "REMINDER " . self::select("description", "cso1_promo_fixed", "status =1 AND id = $id ");
            }

        }

        return $data;
    }



    function getPromoFree($itemId = "")
    {
        $today = date('D', time());
        $q = " SELECT   f.promotionId, f.id as 'promotionFreeId', f.itemId,  f.qty, f.freeItemId, 
                        f.freeQty , p.startDate, p.endDate, p.$today as '$today' 
            FROM cso1_promotion_free AS f 
            LEFT JOIN cso1_promotion AS p ON p.id = f.promotionId
            WHERE p.startDate < unix_timestamp(NOW()) AND unix_timestamp(NOW()) <= p.endDate
            AND p.`status` = 1 AND p.presence = 1 AND f.`status` = 1 AND f.presence = 1
            and (f.itemId = '$itemId'  )  
            and p.$today = 1
        ";


        return count($this->db->query($q)->getResultArray()) > 0 ? $this->db->query($q)->getResultArray()[0] : [
            "qty" => false
        ];

    }


    function getFreeItem($itemId, $qty)
    {
        $today = date('D', time());
        $q2 = "SELECT   i.id AS 'promotionItemId', i.*,
        p.typeOfPromotion,
        FROM_UNIXTIME(p.startDate) AS 'startDate', FROM_UNIXTIME(p.endDate) AS 'endDate', 
        NOW() as 'nowDate',  p.$today as '$today' 
        FROM cso1_promotion_free AS i
        LEFT JOIN cso1_promotion AS p ON p.id = i.promotionId
        WHERE (i.itemId = '$itemId'  ) 
            AND  p.typeOfPromotion = 2  AND  $qty > i.qty
            AND (p.startDate < unix_timestamp(now()) AND unix_timestamp(NOW()) <  p.endDate) 
            AND p.$today = 1 AND p.`status` = 1 AND p.presence = 1 AND i.presence = 1 AND i.`status` = 1";
        $free = $this->db->query($q2)->getResultArray();
        $free['q'] = $q2;
        $data = $free;

        return $data;
    }


    function calculationMemberDiscount($kioskUuid = "", $memberId = '')
    {
        $resp = false;
        if ($kioskUuid != "" && $memberId != "") {
            $discount = (float) self::select("value", "cso1_account", "id = 99 ");
            $q = "SELECT id, originPrice, price, memberDiscountPercent 
            from cso1_kiosk_cart 
            where kioskUuid = '$kioskUuid' and presence = 1  and price > 1
            AND discount = 0 and isPriceEdit = 0 and isSpecialPrice = 0  ";

            $items = $this->db->query($q)->getResultArray();

            foreach ($items as $rec) {

                $newPrice = $rec['originPrice'] - ($rec['originPrice'] * ($discount / 100));

                $this->db->table("cso1_kiosk_cart")->update([
                    "price" => $newPrice,
                    "memberDiscountPercent" => $discount,
                    "memberDiscountAmount" => ($rec['originPrice'] * ($discount / 100)),

                ], " id = '" . $rec['id'] . "'");
            }



            $items = $this->db->query($q)->getResultArray();
            $resp = $items;
        }
        return $resp;
    }


    function checkFreeItem($row)
    {
        // AND p.Fri = 1
        $q = "SELECT f.id as 'promotionFreeId', f.promotionId, f.qty, f.freeItemId, f.freeQty 
        FROM cso1_promotion_free AS f
         JOIN cso1_promotion AS p ON p.id = f.promotionId
         AND  p.typeOfPromotion = 2  AND p.presence = 1  AND f.itemId = '" . $row['itemId'] . "'
         AND (p.startDate < unix_timestamp(now()) AND unix_timestamp(NOW()) <  p.endDate) 
         AND p.`status` = 1 AND p.presence = 1  AND " . $row['qty'] . " >= f.qty
       ";

        $items = $this->db->query($q)->getResultArray();

        //log_message('debug', print_r($items) );
        return count($items) > 0 ? $items[0] : false;
    }


    function promotions_free($kioskUuid)
    {

        // PROMOTION_FREE  :: START   
        $q2 = "SELECT itemId, COUNT(itemId) AS 'qty'
         FROM cso1_kiosk_cart
         WHERE promotionId = '0' AND presence = 1 AND void = 0 and kioskUuid = '$kioskUuid' 
         GROUP BY itemId
         ";
        $ip2 = $this->db->query($q2)->getResultArray();
        foreach ($ip2 as $row) {
            $freeItem = model("promo")->checkFreeItem($row);
            if ($freeItem !== false) {

                if ($row['qty'] >= $freeItem['qty']) {


                    // FREE ITEM INSERT :: START
                    $loops = (int) ($row['qty'] / $freeItem['qty']);
                    $q3 = "SELECT * 
                    FROM cso1_kiosk_cart
                    WHERE promotionId = '0' AND promotionFreeId = '0' and presence = 1 AND void = 0 and itemId = '" . $row['itemId'] . "' 
                    AND kioskUuid = '$kioskUuid' limit $loops;
                    ";
                    //    // echo  $q3;
                    //     $ip3 = $this->db->query($q3)->getResultArray();
                    //     foreach ($ip3 as $rec3) { 
                    //         $this->db->table("cso1_kiosk_cart")->insert([
                    //             "kioskUuid" => $kioskUuid,
                    //             "promotionId" => $freeItem['promotionId'],
                    //             "promotionFreeId" => $freeItem['promotionFreeId'],
                    //             "itemId" => $freeItem['freeItemId'],
                    //             "barcode" => $freeItem['freeItemId'],
                    //             "price" => 0,
                    //             "originPrice" => 0,
                    //             "isFreeItem" => $rec3['id'], 
                    //             "input_date" => date("Y-m-d H:i:s"), 
                    //         ]);
                    //     }
                    //     // FREE ITEM INSERT :: END

                    for ($i = 0; $i < $freeItem['qty']; $i++) {
                        $this->db->table("cso1_kiosk_cart")->update([
                            "promotionId" => $freeItem['promotionId'],
                            "promotionFreeId" => $freeItem['promotionFreeId'],

                        ], " promotionId = 0 AND presence = 1 AND void = 0 and kioskUuid = '$kioskUuid'  ");
                    }
                }
            }
        }

    }

    function applyVoucher($id = "")
    {
        $data = [];
        $apiUrl = $_ENV['voucher'];
        $ch = curl_init($apiUrl);
        $pgv = json_encode([
            "jsonrpc" => "2.0",
            "method" => "call",
            "params" => [
                "service" => "object",
                "method" => "execute_kw",
                "args" => [
                    "sandbox",
                    "2",
                    "4d0b7e61b8cf836959aa048cca53bae4b4031510",
                    "loyalty.card",
                    "write",
                    [
                        [
                            $id
                        ],
                        [
                            "points" => 0
                        ]
                    ]
                ]
            ],
            "id" => 4
        ]);

        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
        ]);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $pgv);

        // Eksekusi cURL dan dapatkan respons
        $response = curl_exec($ch); 
        // Tangani respons atau lakukan sesuatu dengan data yang diterima
        if (curl_errno($ch)) {
            $data = curl_error($ch);
        } else {
            $data = json_decode($response,true);
        }
        curl_close($ch);
        return $data;
    }
}