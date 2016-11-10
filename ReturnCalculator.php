<?php

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */
date_default_timezone_set('GMT');
include_once '/Users/TheNik/Sites/LaVeda/utilities/Debug.php';
define('DB_SERVER', '192.95.31.34');
define('DB_SERVER_USERNAME', 'Nik');
define('DB_SERVER_PASSWORD', 'Sc00ter!');
define('DB_DATABASE', 'VedaBase');

mysql_connect(DB_SERVER, DB_SERVER_USERNAME, DB_SERVER_PASSWORD) or die("Fatal error: db server is down");
mysql_select_db(DB_DATABASE) or die("Fatal error: cant select db");
mysql_query("SET NAMES utf8");
mysql_query("SET CHARACTER SET utf8");

class ReturnCalculator {

    public static function updateVedaReturn($veda, $period) {



        if (mysqli_connect_errno()) {
            echo "Failed to connect to MySQL: " . mysqli_connect_error();
        }



        if ($period == 'inception') {
            $chartColumn = 'inceptionReturn';
            $displayColumn = 'inceptionDisplayReturn';
            $listQuery = "create table if not exists temp (select StockData.date,StockData.adjustedClose,VedaStock.weight "
                    . "from StockData,VedaStock where vedaID = (select vedaID from Veda where name = "
                    . "'$veda') and StockData.stockID = VedaStock.stockID AND date >= (select"
                    . " createdDate from Veda where vedaID = "
                    . "(select vedaID from Veda where name = '$veda')) );";


            goto skip;
        }

        $today = date('Y-m-d');
        $inceptionQuery = "select createdDate from Veda where vedaID = (select vedaID from Veda where "
                . "name = '$veda');";


        $inceptionResult = mysql_query($inceptionQuery) or die(mysql_error());

        $inceptionRow = mysql_fetch_assoc($inceptionResult);
        $inceptionDate = $inceptionRow['createdDate'];


        if ($period == 'annual') {
            $startDate = strtotime('-365 day', strtotime($today));
            $chartColumn = 'annualReturn';
            $displayColumn = 'annualDisplayReturn';
        } elseif ($period == 'monthly') {

            $startDate = strtotime('-30 day', strtotime($today));
            $chartColumn = 'monthlyReturn';
            $displayColumn = 'monthlyDisplayReturn';
        } elseif ($period == 'weekly') {

            $startDate = strtotime('-7 day', strtotime($today));
            $chartColumn = 'weeklyReturn';
            $displayColumn = 'weeklyDisplayReturn';
        } elseif ($period == 'daily') {

            $startDate = strtotime('-1 day', strtotime($today));
            $chartColumn = 'dailyReturn';
            $displayColumn = 'dailyDisplayReturn';
        } else {

            exit("Please enter a valid period (inception, annual, monthly, weekly or daily)");
        }

        $inceptionTime = strtotime($inceptionDate);

        if ($inceptionTime < $startDate) {

            $startDate = date('Y-m-d', $startDate);

            $listQuery = "create table if not exists temp (select StockData.date,StockData.adjustedClose,VedaStock.weight "
                    . "from StockData,VedaStock where vedaID = (select vedaID from Veda where name = "
                    . "'$veda') and StockData.stockID = VedaStock.stockID AND date >= '$startDate' "
                    . ");";
        } else {
            
            $listQuery = "create table if not exists temp (select StockData.date,StockData.adjustedClose,VedaStock.weight "
                    . "from StockData,VedaStock where vedaID = (select vedaID from Veda where name = "
                    . "'$veda') and StockData.stockID = VedaStock.stockID AND date >= '$inceptionDate' order by date asc);";
        }

        skip:


        mysql_query($listQuery) or die(mysql_error());

        $fixedListQuery = "select * from temp where date in (select date from temp group by date having "
                . "COUNT(*) = (select COUNT(*) from VedaStock where vedaID = (select vedaID from"
                . " Veda where name = '$veda'))) order by date,adjustedClose;";
        //echo $fixedListQuery;

        $result = mysql_query($fixedListQuery);

        if (!$result) {
            echo 'MySQL Error: ' . mysql_error();
            exit;
        }

        $startPrice = array();
        $count = 0;

        $row = mysql_fetch_assoc($result);
        $date = $row['date'];


        while ($row && ($currDate = $row['date']) == $date) {

            $startPrice[$count] = floatval($row['adjustedClose']);
            $count++;
            $row = mysql_fetch_assoc($result);
        }

        $seconds = strtotime($date) * 1000;
        $json[] = array($seconds, 0.00);

        while ($row) {

            $date = $row['date'];
            $sum = 0;

            for ($i = 0; $i < sizeof($startPrice); $i ++) {

                $weight = $row['weight'];
                $price = floatval($row['adjustedClose']);
                $sum += $weight * (($price / $startPrice[$i]) - 1);
                $row = mysql_fetch_assoc($result);
            }

            $seconds = strtotime($date) * 1000;
            $json[] = array($seconds, round($sum, 3));
        }
        echo "<div style='width: 95%; padding: 5px; margin: 0 auto; background-color: lightyellow; border: 1px solid red; display: block;'>";
        echo "<pre style=\"background-color: lightyellow; border: none;\">";
        $json = json_encode($json);
        print_r($json);
        echo "</pre>";
        echo "</div>";

        $updateChartQuery = "update Veda set $chartColumn = '$json' where name = '$veda';";
        //echo $updateQuery;
        mysql_query($updateChartQuery);

        if (isset($sum)) {
            $updateDisplayQuery = "update Veda set $displayColumn = $sum where name = '$veda';";

            mysql_query($updateDisplayQuery);
        }

        $dropQuery = "drop table temp;";
        mysql_query($dropQuery);
    }

    public static function updateVedaReturns() {
        $query = "select name from Veda;";
        $result = mysql_query($query) or die(mysql_error());

        while ($row = mysql_fetch_assoc($result)) {
            $vedaName = $row['name'];
            ReturnCalculator::updateVedaReturn("$vedaName", "inception");
            ReturnCalculator::updateVedaReturn("$vedaName", "annual");
            ReturnCalculator::updateVedaReturn("$vedaName", "monthly");
            ReturnCalculator::updateVedaReturn("$vedaName", "weekly");
            ReturnCalculator::updateVedaReturn("$vedaName", "daily");
        }
    }

}

//ReturnCalculator::updateVedaReturn('VedaTech', 'weekly');
ReturnCalculator::updateVedaReturns();





