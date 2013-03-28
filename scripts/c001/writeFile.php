<?php
    require('/var/www/scripts/class/Curl.php');
    require('/var/www/scripts/class/phpQuery.php');

    $curl = new Curl();
    $mainUrl = 'http://www.urbanoutfitters.com';
    $saleUrl = $mainUrl . '/urban/catalog/category.jsp';

    $category = array('men' => '?&id=SALE_M',
            'women' => '?&id=SALE_W',
            'apt' => '?&id=SALE_APT');

    foreach($category as $type=>$firstPage)
    { 
        print " - Working on " . $type . "\n";
        print "\t - Retrieving " . $type . " sale page 1 ... ";
        $html = $curl->get($saleUrl . $firstPage);
        $pq = phpQuery::newDocumentHTML($html);

        /**
         * Save the first page
         */
        $file = fopen('files/' . $type . '_001.html', 'w');
        fwrite($file, $html);
        fclose($file);
        print "Done\n";

        /**
         * Figure out how many more files we need to scrap
         */
        $pageCount = $pq->find('span.category-pagination-pages:first')->html();
        $pageCount = pq($pageCount)->remove('a')->html();
        $pageCount = explode('of', $pageCount);
        $pageCount = trim(preg_replace('/[^0-9]/', '',$pageCount[1]));

        for($i = 2; $i <= $pageCount; $i++)
        {
            $nextPage = '';
            foreach($pq->find('span.category-pagination-pages')->children('a') as $pagination)
            {
                $nextPage = pq($pagination)->attr('href');
            }
            if($nextPage)
            {
                $timeLimit = rand(0, 5);
                print "\t - Waiting " . $timeLimit . " s\n";
                sleep($timeLimit);

                print "\t - Retrieving " . $type . " sale page " . $i . " out of " . $pageCount . " ... ";
                $html = $curl->get($saleUrl . $nextPage);
                $pq = phpQuery::newDocumentHTML($html);
                $file = fopen('files/' . $type . '_' . sprintf('%03s', $i) . '.html', 'w');
                fwrite($file, $html);
                fclose($file);
                print "Done\n";
            }
        }
    }
    exit(0);
?>
