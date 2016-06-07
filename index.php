<?php
// Google 搜索结果采集器

set_time_limit(0);
set_magic_quotes_runtime(0);
date_default_timezone_set('PRC');    

/**
 * 判断输入的搜索内容是否UTF8字符集
 *
 * @param string $word 待检查的文本
 * @return boolean
*/
function is_utf8( $word ){
    if ( preg_match( "/^([".chr( 228 )."-".chr( 233 )."]{1}[".chr( 128 )."-".chr( 191 )."]{1}[".chr( 128 )."-".chr( 191 )."]{1}){1}/", $word ) == true || preg_match( "/([".chr( 228 )."-".chr( 233 )."]{1}[".chr( 128 )."-".chr( 191 )."]{1}[".chr( 128 )."-".chr( 191 )."]{1}){1}\$/", $word ) == true || preg_match( "/([".chr( 228 )."-".chr( 233 )."]{1}[".chr( 128 )."-".chr( 191 )."]{1}[".chr( 128 )."-".chr( 191 )."]{1}){2,}/", $word ) == true ){
        return true;
    }
    return false;
}

/**
 * 读取 Google 查询结果
 * 
 * @param string $url 拼装好的查询 URL 
 * @return string
*/
function getGoogleSearch( $url ){
    $headers = array( "User-Agent:Mozilla/5.0 (Windows; U; Windows NT 5.1; zh-CN; rv:1.9.1.9) Gecko/20100315 Firefox/3.5.9", "Accept: text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8", "Accept-Language: zh-cn,zh;q=0.5", "Accept-Charset: GB2312,utf-8;q=0.7,*;q=0.7", "Keep-Alive:300", "Connection:keep-alive", "Cookie: PREF=ID=4e6b51a77e350a54:U=7ebe22efb1649b6e:FF=1:LD=zh-CN:NW=1:TM=1283615001:LM=1286508781:S=E7ZONfJAlPMXLNg5; NID=40=QjA5nNXzJ0faTieT2C_aQLh_Nxg33xRsfjSsGyJXbGiT5osTiXYAtI0wYUFXHj9pqkXKb8RpHJIgl8t6mYwQp_BYRjCOfjP4P5d9fReMktJfd7_nAnzICsnbB7mj7_sW" );
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url );
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    return curl_exec($ch);
}

/**
 * 解析关键字查询
 *
 * @param string $word 查询关键字
 * @param integer $page 查询页码
 * @return mixed 查询成功返回数组，查询失败直接输出查询结果并停止运行
*/
function query($word, $page = 1){
    $arrResult = array();
    
    $rows = 20;
    $word = strtr( $word, ' ', "+" );
    $word = strtr( $word, '_', "+" );
    $start = ( $page - 1 ) * $rows;

    if ($word == ''){
        return false;
    }

    if (is_utf8( $word) ){
        $search = rawurlencode( $word );
    }else{
        $word = mb_convert_encoding($word, "UTF-8", "GBK");
        $word = rawurlencode( $word );
    }

    $so = preg_replace( "/%2B/i", "+", $word );
    $google_url = 'http://www.google.com.hk/search?hl=zh-CN&q='. $so . '&start=' . $start . '&num=' . $rows;
    $strSearchResult = getGoogleSearch( $google_url );
    
    $arrResult['so'] = $so;
    $numCount = preg_match_all( "/找到约(.*)条结果|获得约(.*)条结果/", $strSearchResult, $arrSearchCount );

    if(1 == $numCount){
        $arrResult['num'] = str_replace(',', '', $arrSearchCount[1][0]);
    }

    $numCount = preg_match_all( "/<h3 class=\"r\"><a href=\"(.*)\"(.*)>(.*)<\/a><\/h3>/isU", $strSearchResult, $arrSearchResult );
    $arrResult['link'] = $arrSearchResult[1];
    $arrResult['label'] = $arrSearchResult[3];

    if(0 == $numCount && !empty($strSearchResult)){
        echo $strSearchResult;exit();
    }
    
    return $arrResult;
}

$wd = isset($_GET['wd']) ? $_GET['wd'] : '';
$mp = isset($_GET['mp']) ? $_GET['mp'] : 0;
$cp = isset($_GET['cp']) ? $_GET['cp'] : 0;

if($cp > $mp && $mp > 1){
    die('采集完毕');
}

$file = urlencode($wd) . '.download';
$arrSearch = query($wd, $cp + 1);
$md5 = md5(var_export($arrSearch, true));

if(isset($_GET['md5']) && $_GET['md5'] == $md5){
    die('采集的数据重复，程序停止运行。');
}

$url = '<meta http-equiv="refresh" content="2;URL=./googlext.php?wd=' . $arrSearch['so'] . '&mp=' . $mp . '&cp=' . ($cp +1) . '&md5=' . $md5 . '">';

echo '<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.01 Transitional//EN" "http://www.w3c.org/TR/1999/REC-html401-19991224/loose.dtd">
<HTML xmlns="http://www.w3.org/1999/xhtml"><HEAD>
<title>',$wd,'</title>
<META http-equiv=Content-Type content="text/html; charset=utf-8">';
echo $mp > 1 ? $url : '';
echo '</HEAD>
<BODY>
<DIV><FORM action="">
搜索：<INPUT id="wd" name="wd" autocomplete="off" size="48" value="', $wd ,'">
<INPUT type="submit" value="搜 索"></FORM>';

if(is_array($arrSearch) && count($arrSearch)){
   

    echo '<div>关键词:', $wd,' 搜索到<EM>' , $arrSearch['num'] , '</EM>条结果，采集进度', $cp + 1, '/' . $mp . '</div><hr><DIV id=main>';
    foreach ( $arrSearch['link'] as $key => $val ){
        echo '<a href="' . substr($val, 7) . '" target=_blank>' . $arrSearch['label'][$key] . '</a><br>';

    } 
    $cp++;
    echo "<br/><a href='b.php?wd=$wd&cp=$cp&num=$num'>next</a>";
}
echo "</DIV></BODY></HTML>";
?>
