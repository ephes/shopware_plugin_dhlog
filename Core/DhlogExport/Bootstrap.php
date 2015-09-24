<?php

class Shopware_Plugins_Core_DhlogExport_Bootstrap extends Shopware_Components_Plugin_Bootstrap
{

    public function getVersion()
    {
        return '0.0.1';
    }

    public function getLabel()
    {
        return 'Slogan of the day';
    }

    public function install()
    {
        $this->subscribeEvent(
            'Enlight_Controller_Action_PostDispatchSecure_Frontend',
            'onFrontendPostDispatch'
        );

        $this->createConfig();

        return true;
    }
    public function onFrontendPostDispatch(Enlight_Event_EventArgs $args)
    {
        /** @var \Enlight_Controller_Action $controller */
        $controller = $args->get('subject');
        $view = $controller->View();

        //$view->addTemplateDir(
        //    __DIR__ . '/Views'
        //);
        $view->addTemplateDir($this->Path() . 'Views');

        $view->assign('slogan', $this->getSlogan());
        $view->assign('sloganSize', $this->Config()->get('font-size'));
        $view->assign('italic', $this->Config()->get('italic'));

    }

    public function getSlogan()
    {
        $this->exportCustomers();
        $this->exportOrders();
        return array_rand(
            array_flip(
                array(
                    'An apple a day keeps the doctor away',
                    'Let’s get ready to rumble',
                    'A rolling stone gathers no moss',
                )
            )
        );
    }
    private function createConfig()
    {
        $this->Form()->setElement(
            'select',
            'font-size',
            array(
                'label' => 'Font size',
                'store' => array(
                    array(12, '12px'),
                    array(18, '18px'),
                    array(25, '25px')
                ),
                'value' => 12
            )
        );

        $this->Form()->setElement('boolean', 'italic', array(
            'value' => true,
            'label' => 'Italic'
        ));
    }

    public function exportCustomers()
    {
        $elements = array("_DEWERK", "_DKNR", "_DIMP", "_DASTT", "_DZA", "_DNAM1", "_DNAM2", "_DSTR",
            "_DPLZ", "_DORT", "_DCMNA", "_DBSL1", "_DLAND");
        $fixed_values = array(
            "_DEWERK" => "PE",
            "_DIMP" => "I1",
            "_DASTT" => "",
            "_DZA" => "",
            "_DNAM2" => "",
            "_DBSL1" => 22,
        );
        $lookup = array(
            "_DKNR" => "userID",
            "_DNAM1" => array("billing_firstname", "billing_lastname"),
            "_DSTR" => "billing_street",
            "_DPLZ" => "billing_zipcode",
            "_DORT" => "billing_city",
            "_DCMNA" => "email",
            "_DLAND" => "billing_country_iso",

        );
        $customers = $this->getCustomers();
        $xml = new DOMDocument('1.0', 'UTF-8');
        $xml->xmlStandalone = false;
        $xml->formatOutput = true;
        $customer_list = $xml->createElement('ordercustomerlist');
        foreach ($customers as $customer) {
            $order_customer = $xml->createElement('ordercustomer');
            foreach($elements as $elem_name) {
                $element = $xml->createElement($elem_name);
                if (array_key_exists($elem_name, $fixed_values)) {
                    $element->nodeValue = $fixed_values[$elem_name];
                } else {
                    $cols = $lookup[$elem_name];
                    if (is_array($cols)) {
                        $values = array();
                        foreach($cols as $col) {
                            array_push($values, $customer[$col]);
                        }
                        $element->nodeValue = implode(" ", $values);
                    } else {
                        $element->nodeValue = $customer[$cols];
                    }

                }
                $order_customer->appendChild($element);
            }
            $customer_list->appendChild($order_customer);
        }
        $xml->appendChild($customer_list);
        $xml->save("/tmp/customers.xml");
    }

    public function getCustomers()
    {
        $sDB = Shopware()->Adodb();
        $sql = "
                SELECT
                    `u`.`id`,
                    `u`.`id` AS userID,
                    `b`.`company` AS `billing_company`,
                    `b`.`department` AS `billing_department`,
                    `b`.`salutation` AS `billing_salutation`,
                    `b`.`customernumber`,
                    `b`.`firstname` AS `billing_firstname`,
                    `b`.`lastname` AS `billing_lastname`,
                    `b`.`street` AS `billing_street`,
                    `b`.`zipcode` AS `billing_zipcode`,
                    `b`.`city` AS `billing_city`,
                    `b`.`phone` AS `billing_phoney`,
                    `b`.`fax` AS `billing_fax`,
                    `b`.`countryID` AS `billing_countryID`,
                    `b`.`ustid`,
                    `b`.`stateID` AS `billing_stateID`,
                    `ba`.`text1` AS `billing_text1`,
                    `ba`.`text2` AS `billing_text2`,
                    `ba`.`text3` AS `billing_text3`,
                    `ba`.`text4` AS `billing_text4`,
                    `ba`.`text5` AS `billing_text5`,
                    `ba`.`text6` AS `billing_text6`,
                    `s`.`company` AS `shipping_company`,
                    `s`.`department` AS `shipping_department`,
                    `s`.`salutation` AS `shipping_salutation`,
                    `s`.`firstname` AS `shipping_firstname`,
                    `s`.`lastname` AS `shipping_lastname`,
                    `s`.`street` AS `shipping_street`,
                    `s`.`zipcode` AS `shipping_zipcode`,
                    `s`.`city` AS `shipping_city`,
                    `s`.`countryID` AS `shipping_countryID`,
                    `s`.`stateID` AS `shipping_stateID`,
                    `sa`.`text1` AS `shipping_text1`,
                    `sa`.`text2` AS `shipping_text2`,
                    `sa`.`text3` AS `shipping_text3`,
                    `sa`.`text4` AS `shipping_text4`,
                    `sa`.`text5` AS `shipping_text5`,
                    `sa`.`text6` AS `shipping_text6`,
                    `u`.`email`,
                    `u`.`paymentID` ,
                    `u`.`newsletter` ,
                    `u`.`affiliate` ,
                    `u`.`customergroup`,
                       u.subshopID ,
                    bc.countryname as billing_country,
                    bc.countryiso as billing_country_iso,
                    bca.name as billing_country_area,
                    bc.countryen as billing_country_en,
                    sc.countryname as shipping_country,
                    sc.countryiso as shipping_country_iso,
                    sca.name as shipping_country_area,
                    sc.countryen as shipping_country_en
                FROM
                    `s_user` as `u`
                LEFT JOIN `s_user_billingaddress` as `b` ON (`b`.`userID`=`u`.`id`)
                LEFT JOIN `s_user_shippingaddress` as `s` ON (`s`.`userID`=`u`.`id`)
                LEFT JOIN s_core_countries bc ON bc.id = b.countryID
                LEFT JOIN s_core_countries sc ON sc.id = s.countryID
                LEFT JOIN s_core_countries_areas bca
                    ON bc.areaID = bca.id
                LEFT JOIN s_core_countries_areas sca
                    ON sc.areaID = sca.id
                LEFT JOIN s_user_billingaddress_attributes ba
                    ON b.id = ba.billingID
                LEFT JOIN s_user_shippingaddress_attributes sa
                    ON s.id = sa.shippingID";
        $result = $sDB->GetAssoc($sql, false, $force_array=true);
        $myfile = fopen("/tmp/foo.txt", "w") or die("Unable to open file!");
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

    public function exportOrders()
    {
        $elements = array("VSWERK", "VSHERK", "VSKDNR", "VSPJNR", "VSAUFN", "VSPOS", "VSAART", "VBEM",
            "VSANR", "VSBPRS", "VSBMNG", "VSZA", "VSHERK", "VSNAME1V", "VSSTRV", "VSLANDV", "VSPLZV",
            "VSORTV", "VSNAME1R", "VSSTRR", "VSLANDR", "VSPLZR", "VSORTR", "VSBSL1", "VSGRPZ");
        $fixed_values = array(
            "VSWERK" => "PE",
            "VSHERK" => "X1",
            "VSPJNR" => "0",
            "VSPOS" => "999",
            "VSAART" => "L",
            "VSZA" => "U",
            "VSHERK" => "X",
            "VSBSL1" => "10",
            "VSGRPZ" => "3",
        );
        $lookup = array(
            "VSKDNR" => "userID",
            "VSAUFN" => "ordernumber",
            "VBEM" => "customercomment",
            "VSANR" => "articleID",
            "VSBPRS" => "invoice_amount",
            "VSBMNG" => "quantity",
            "VSNAME1V" => "shipping_name",
            "VSSTRV" => "shipping_street",
            "VSLANDV" => "shipping_country",
            "VSPLZV" => "shipping_zipcode",
            "VSORTV" => "shipping_city",
            "VSNAME1R" => "billing_name",
            "VSSTRR" => "billing_street",
            "VSLANDR" => "billing_country",
            "VSPLZR" => "billing_zipcode",
            "VSORTR" => "billing_city",
        );
        $orders = $this->getOrders();
        $xml = new DOMDocument('1.0', 'UTF-8');
        $xml->xmlStandalone = false;
        $xml->formatOutput = true;
        $order_list = $xml->createElement('orderlist');
        foreach ($orders as $order) {
            $orders_elem = $xml->createElement('orders');
            foreach($elements as $elem_name) {
                $element = $xml->createElement($elem_name);
                if (array_key_exists($elem_name, $fixed_values)) {
                    $element->nodeValue = $fixed_values[$elem_name];
                } else {
                    $element->nodeValue = $order[$lookup[$elem_name]];
                }
                $orders_elem->appendChild($element);
            }
            $order_list->appendChild($orders_elem);
        }
        $xml->appendChild($order_list);
        $xml->save("/tmp/orders.xml");
    }

    public function getOrders()
    {
        $sDB = Shopware()->Adodb();
        $sql = "
            select
              d.orderID,
              d.articleID,
              d.quantity,
              o.ordernumber,
              o.userID,
              o.customercomment,
              o.invoice_amount,
              CONCAT_WS(' ', s.firstname, s.lastname) as shipping_name,
              s.street as shipping_street,
              sc.countryiso as shipping_country,
              s.zipcode as shipping_zipcode,
              s.city as shipping_city,
              CONCAT_WS(' ', b.firstname, b.lastname) as billing_name,
              b.street as billing_street,
              sc.countryiso as billing_country,
              b.zipcode as billing_zipcode,
              b.city as billing_city
            FROM
              s_order_details as d
            JOIN
              s_order as o
            ON
              d.orderID = o.id
            JOIN
              s_articles as a
            ON
              d.articleID = a.id
            JOIN
              s_order_shippingaddress as s
            ON
              s.orderID = d.orderID
            JOIN
              s_core_countries as sc
            ON
              s.countryID = sc.id

            JOIN
              s_order_billingaddress as b
            ON
              b.orderID = d.orderID
            JOIN
              s_core_countries as bc
            ON
              b.countryID = bc.id
            WHERE
              o.ordernumber > 0
        ";
        $result = $sDB->GetAll($sql);
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
}

