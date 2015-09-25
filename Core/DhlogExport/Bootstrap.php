<?php

class Shopware_Plugins_Core_DhlogExport_Bootstrap extends Shopware_Components_Plugin_Bootstrap
{

    public function getVersion()
    {
        return '0.0.1';
    }

    public function getLabel()
    {
        return 'Dhlog Export Plugin';
    }

    private function createConfig()
    {
        $this->Form()->setElement(
            'text',
            'orders_path',
            array(
                'label' => 'path of orders export',
                'value' => '/tmp/orders.xml'
            )
        );

        $this->Form()->setElement(
            'text',
            'customers_path',
            array(
                'label' => 'path of customers export',
                'value' => '/tmp/customers.xml'
            )
        );
    }

    public function install()
    {
        $this->subscribeEvent(
            'Enlight_Controller_Action_PostDispatchSecure_Frontend',
            'onFrontendPostDispatch'
        );

        $this->subscribeEvent(
            'Shopware_CronJob_DhlogExport',
            'onRun'
        );

        $this->createConfig();
        $this->createCronJob("Dhlog Export", "DhlogExport", 600);

        return true;
    }

    public function onRun(Enlight_Components_Cron_EventArgs $job)
    {
        $this->exportDhlog();
    }

    public function onFrontendPostDispatch(Enlight_Event_EventArgs $args)
    {
        $this->exportDhlog();
    }


    public function getCustomers($orderIds)
    {
        $orderIds = Shopware()->Db()->quote($orderIds);
        $sql = "
            SELECT
                b.orderID,
                ub.customernumber,
                CONCAT_WS(' ', ub.firstname, ub.lastname) as billing_name,
                ub.street AS billing_street,
                ub.zipcode AS billing_zipcode,
                ub.city AS billing_city,
                bc.countryiso AS billing_countryiso,
                ub.additional_address_line1,
                ub.additional_address_line2,
                u.email
            FROM
                s_order_billingaddress as b
            LEFT JOIN s_user_billingaddress as ub
                ON ub.userID = b.userID
            LEFT JOIN s_user as u
                ON b.userID = u.id
            LEFT JOIN s_core_countries as bc
                ON bc.id = ub.countryID
            WHERE b.orderID IN ($orderIds)
        ";
        $rows = Shopware()->Db()->fetchAssoc($sql);
        $myfile = fopen("/tmp/customers.txt", "w") or die("Unable to open file!");
        ob_start();
        var_dump($rows);
        $result = ob_get_clean();
        fwrite($myfile, $result);
        fwrite($myfile, $orderIds);
        fclose($myfile);
        return $rows;
    }

    public function getPositions($orderIds)
    {
        $orderIds = Shopware()->Db()->quote($orderIds);
        $sql = "
            SELECT
                d.id as orderdetailsID,
                d.orderID,
                d.articleID,
                d.quantity,
                d.ordernumber,
                d.price*d.quantity as invoice,
                d.status,
                d.esdarticle,
                d.taxID,
                t.tax
            FROM s_order_details as d
            LEFT JOIN s_core_tax as t
            ON t.id = d.taxID
            WHERE d.orderID IN ($orderIds) AND d.articleID > 0
            ORDER BY orderdetailsID ASC
        ";
        $result = Shopware()->Db()->fetchAll($sql);
        $rows = array();
        foreach ($result as $row) {
            $rows[$row['orderID']][$row['orderdetailsID']] = $row;
        }
        $myfile = fopen("/tmp/positions.txt", "w") or die("Unable to open file!");
        ob_start();
        var_dump($rows);
        $result = ob_get_clean();
        fwrite($myfile, $result);
        fwrite($myfile, $orderIds);
        fclose($myfile);
        return $rows;
    }

    public function getOrders($sendTime)
    {
        $sql = "
            SELECT
              o.id,
              o.ordernumber,
              o.userID,
              ub.customernumber,
              o.customercomment,
              o.invoice_amount,
              p.name as payment_method,
              CONCAT_WS(' ', sa.firstname, sa.lastname) as shipping_name,
              sa.street as shipping_street,
              sc.countryiso as shipping_country,
              sa.zipcode as shipping_zipcode,
              sa.city as shipping_city,
              sa.additional_address_line1 as shipping_address_line1,
              sa.additional_address_line2 as shipping_address_line2,
              CONCAT_WS(' ', ba.firstname, ba.lastname) as billing_name,
              ba.street as billing_street,
              sc.countryiso as billing_country,
              ba.zipcode as billing_zipcode,
              ba.city as billing_city,
              ba.additional_address_line1 as billing_address_line1,
              ba.additional_address_line2 as billing_address_line2
            FROM s_order as o
            LEFT JOIN s_user_billingaddress as ub
            ON o.userID = ub.userID
            LEFT JOIN s_core_states as scs
            ON  (o.status = scs.id)
            LEFT JOIN s_core_paymentmeans as p
            ON  (o.paymentID = p.id)
            LEFT JOIN s_premium_dispatch as pd
            ON  (o.dispatchID = pd.id)
            LEFT JOIN s_order_shippingaddress as sa
            ON (sa.orderID = o.id)
            LEFT JOIN s_core_countries as sc
            ON (sa.countryID = sc.id)
            LEFT JOIN s_order_billingaddress as ba
            ON (ba.orderID = o.id)
            LEFT JOIN s_core_countries as bc
            ON (ba.countryID = bc.id)
            WHERE scs.id = 0
            ";
        $result = Shopware()->Db()->fetchAssoc($sql);
        $myfile = fopen("/tmp/orders.txt", "w") or die("Unable to open file!");
        foreach ($result as $row) {
            $line = '';
            foreach ($row as $key => $value) {
                $line = $line . "$key: $value ";
            }
            $line = $line . "\n";
            fwrite($myfile, $line);
        }
        fclose($myfile);
        return $result;
    }

    public function exportOrders($orders, $orderIds)
    {
        $orders_elements = array(
            "VSWERK", "VSHERK", "VSKDNR", "VSPJNR", "VSAUFN", "VSPOS", "VSAART", "VBEM",
            "VSANR", "VSBPRS", "VSBMNG", "VSZA", "VSHERK", "VSNAME1V", "VSNAME2V", "VSNAME3V",
            "VSSTRV", "VSLANDV", "VSPLZV", "VSORTV", "VSNAME1R", "VSNAME2R", "VSNAME3R",
            "VSSTRR", "VSLANDR", "VSPLZR", "VSORTR", "VSBSL1", "VSGRPZ");
        $orders_fixed_values = array(
            "VSWERK" => "PE",
            "VSPJNR" => "0",
            "VSAART" => "L",
            "VSBSL1" => "25",
            "VSGRPZ" => "3-25",
        );
        $orders_lookup = array(
            "VSKDNR" => "customernumber",
            "VSAUFN" => "ordernumber",
            "VBEM" => "customercomment",
            "VSANR" => "articleID",
            "VSBPRS" => "invoice_amount",
            "VSBMNG" => "quantity",
            "VSNAME1V" => "shipping_name",
            "VSNAME2V" => "shipping_address_line1",
            "VSNAME3V" => "shipping_address_line2",
            "VSSTRV" => "shipping_street",
            "VSLANDV" => "shipping_country",
            "VSPLZV" => "shipping_zipcode",
            "VSORTV" => "shipping_city",
            "VSNAME1R" => "billing_name",
            "VSNAME2R" => "billing_address_line1",
            "VSNAME3R" => "billing_address_line2",
            "VSSTRR" => "billing_street",
            "VSLANDR" => "billing_country",
            "VSPLZR" => "billing_zipcode",
            "VSORTR" => "billing_city",
        );
        $billing_lookup = array(
            "prepayment" => "V",
            "invoice" => "U",
            "sepa" => "U",
            "paypal" => "P",
        );
        $shipping_set = array("VSKDNR", "VSAUFN");
        $positions = $this->getPositions($orderIds);
        $xml = new DOMDocument('1.0', 'UTF-8');
        $xml->xmlStandalone = false;
        $xml->formatOutput = true;
        $order_list = $xml->createElement('orderlist');
        foreach ($orders as $order) {
            $normal_tax = False;
            foreach (array_values($positions[$order["id"]]) as $i => $position) {
                $vsherk_first = True;
                $orders_elem = $xml->createElement('orders');
                if ($position["tax"] == 19.00) {
                    $normal_tax = True;
                }
                foreach ($orders_elements as $elem_name) {
                    $element = $xml->createElement($elem_name);
                    if ($elem_name == "VSPOS") {
                        $element->nodeValue = $i + 1;
                    } elseif ($elem_name == "VSZA") {
                        $element->nodeValue = $billing_lookup[$order["payment_method"]];
                    } elseif ($elem_name == "VSHERK") {
                        if ($vsherk_first) {
                            $element->nodeValue = "X1";
                            $vsherk_first = False;
                        } else {
                            $element->nodeValue = "X";
                            $vsherk_first = True;
                        }
                    } elseif (array_key_exists($elem_name, $orders_fixed_values)) {
                        $element->nodeValue = $orders_fixed_values[$elem_name];
                    } else {
                        $element->nodeValue = $order[$orders_lookup[$elem_name]];
                    }
                    $orders_elem->appendChild($element);
                }
                $order_list->appendChild($orders_elem);
                // tax
                $orders_elem = $xml->createElement('orders');
                foreach ($orders_elements as $elem_name) {
                    $element = $xml->createElement($elem_name);
                    if ($elem_name == "VSPOS") {
                        $element->nodeValue = 999;
                    } elseif ($elem_name == "VSANR") {
                        if ($normal_tax) {
                            $element->nodeValue = "VERSAND2";
                        } else {
                            $element->nodeValue = "VERSAND1";
                        }
                    } elseif ($elem_name == "VSZA") {
                        $element->nodeValue = $billing_lookup[$order["payment_method"]];
                    } elseif (in_array($elem_name, $shipping_set)) {
                        $element->nodeValue = $order[$orders_lookup[$elem_name]];
                    } elseif (array_key_exists($elem_name, $orders_fixed_values)) {
                        $element->nodeValue = $orders_fixed_values[$elem_name];
                    } else {
                        $element->nodeValue = "";
                    }
                    $orders_elem->appendChild($element);
                }
                $order_list->appendChild($orders_elem);
            }
        }
        $xml->appendChild($order_list);
        $xml->save($this->Config()->get('orders_path'));
    }

    public function exportCustomers($orderIds)
    {
        $elements = array("_DEWERK", "_DKNR", "_DIMP", "_DASTT", "_DZA", "_DNAM1", "_DNAM2", "_DSTR",
            "_DPLZ", "_DORT", "_DCMNA", "_DBSL1", "_DLAND");
        $fixed_values = array(
            "_DEWERK" => "PE",
            "_DIMP" => "I1",
            "_DASTT" => "",
            "_DZA" => "",
            "_DBSL1" => 25,
        );
        $lookup = array(
            "_DKNR" => "customernumber",
            "_DNAM1" => "billing_name",
            "_DNAM2" => "billing_address_line1",
            "_DSTR" => "billing_street",
            "_DPLZ" => "billing_zipcode",
            "_DORT" => "billing_city",
            "_DCMNA" => "email",
            "_DLAND" => "billing_country_iso",

        );
        $customers = $this->getCustomers($orderIds);
        $xml = new DOMDocument('1.0', 'UTF-8');
        $xml->xmlStandalone = false;
        $xml->formatOutput = true;
        $customer_list = $xml->createElement('ordercustomerlist');
        foreach ($customers as $customer) {
            $order_customer = $xml->createElement('ordercustomer');
            foreach ($elements as $elem_name) {
                $element = $xml->createElement($elem_name);
                if (array_key_exists($elem_name, $fixed_values)) {
                    $element->nodeValue = $fixed_values[$elem_name];
                } else {
                    $element->nodeValue = $customer[$lookup[$elem_name]];
                }
                $order_customer->appendChild($element);
            }
            $customer_list->appendChild($order_customer);
        }
        $xml->appendChild($customer_list);
        $xml->save($this->Config()->get('customers_path'));
    }

    public function exportDhlog()
    {
        $orders = $this->getOrders();
        $orderIds = array_keys($orders);
        $this->exportOrders($orders, $orderIds);
        $this->exportCustomers($orderIds);
    }
}