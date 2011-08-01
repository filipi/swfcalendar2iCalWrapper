<?PHP
  /***************************************************************
   * Name:      iCalWrapper.php
   * Purpose:   Convert a XML calendar to the iCal standard
   * Author:    Filipi Vianna (filipi@pucrs.br)
   * Created:   2011-07-26
   * Copyright: IDEIA (http://wwww.pucrs.br/ideia)
   * License: GNU GPL
   **************************************************************/

$address = "http://www3.pucrs.br/portal/pls/portal/portal_admin.xml_calendar";

$type = intval(trim($_GET['type']));

function stripAccents($string){
  return strtr($string, "áéíóúàèìòäëëïöüâêîôûãõçñÁÉÍÓÚÀÈÌÒÙÄËÏÖÜÂÊÎÔÛÃÕÇÑºª", 
	       "aeiouaeiouaeiouaeiouaocnAEIOUAEIOUAEIOUAEIOUAOCNoa");
}

// Timeout test, to avoid locking the client browser and/or the web server
$timeout = 1;
$old = ini_set('default_socket_timeout', $timeout);
$file = fopen($address, 'r');
if ($file){
  ini_set('default_socket_timeout', $old);
  stream_set_timeout($file, $timeout);
  stream_set_blocking($file, 0);
  while (!feof($file))
    $buffer .= fgets($file);
  fclose($file);

  $buffer = preg_replace("/(\r|\n)/", '', $buffer); // Strip line breaks
  $buffer = preg_replace('/>\s*?</i', '><', $buffer); // Strip spaces between tags
  $buffer = preg_replace('/(<\/)(.*?)(>)/i', '</${2}><BREAKHERE>', $buffer); // Insert a breking tag after all closer tags

  $buffer = str_replace('><data', '><BREAKHERE><data', $buffer);
  $buffer = str_replace('><month', '><BREAKHERE><month', $buffer);
  $buffer = str_replace('><event>', '><BREAKHERE><event>', $buffer);
  $buffer = str_replace('><day', '><BREAKHERE><day', $buffer);
  $buffer = str_replace('><title', '><BREAKHERE><title', $buffer);

  $month = 0;  $day = 0;  $event = 0;  
  $items = explode("<BREAKHERE>", $buffer); 

  echo "BEGIN:VCALENDAR\r\n";
  echo "VERSION:2.0\r\n";
  echo "PRODID:-//hacksw/handcal//NONSGML v1.0//EN\r\n";

  echo "CALSCALE:GREGORIAN\r\n";
  echo "METHOD:PUBLISH\r\n";
  echo "X-WR-TIMEZONE:UTC\r\n";
  echo "X-WR-CALDESC:PUCRS Holidays\r\n";

  foreach($items as $item){
    if (strpos("_ " . $item, '<data'))
      $current_year = preg_replace('/(.*)?(curr_y=")(\d+)(.*)/i', '${3}', $item);
    else
      if (strpos("_ " . $item, '<month')){
	if (trim($item)) {
	  $month++;
          $month_id = preg_replace('/(.*)?(id=")(\d+)(.*)/i', '${3}', $item);          
	}
      }
      else
	if (strpos("_ " . $item, '<day')){
	  if (trim($item)) {
	    $day++;
            $day_id = preg_replace('/(.*)?(id=")(\d+)(.*)/i', '${3}', $item);          
	  }
	}
	else
	  if (strpos("_ " . $item, '<event')){
	    if (trim($item)) $event++;
	  }
	  else
	    if (strpos("_ " . $item, 'title'))
	      $title = preg_replace('/(.*)?(<title>)(.*)?<\/title>/i', '${3}', $item);
	    else
	      if (strpos("_ " . $item, 'link'))
		$link = preg_replace('/(.*)?(<link>)(.*)?<\/link>/i', '${3}', $item);
	      else
		if (strpos("_ " . $item, 'start_date'))
		  $start_date = preg_replace('/(.*)?(<start_date>)(.*)?<\/start_date>/i', '${3}', $item);
		else
		  if (strpos("_ " . $item, 'end_date'))
		    $end_date = preg_replace('/(.*)?(<end_date>)(.*)?<\/end_date>/i', '${3}', $item);
		  else
		    if (strpos("_ " . $item, 'location'))
		      $location = preg_replace('/(.*)?(<location>)(.*)?<\/location>/i', '${3}', $item);
		    else
		      if (strpos("_ " . $item, 'type_id'))
			$type_id = preg_replace('/(.*)?(<type_id>)(.*)?<\/type_id>/i', '${3}', $item);
		      else
			if (strpos("_ " . $item, '</event>')){
			  if (intval($type) == $type_id || !$type){
			    echo "BEGIN:VEVENT\r\n";
			    echo "DTSTART;VALUE=DATE:" . $current_year;
			    echo ((strlen(trim($month_id))<2) ? "0" : "");
			    echo $month_id . ((strlen(trim($day_id))<2) ? "0" : "") . $day_id  . "\r\n";
			    if (strpos("_" . trim($end_date), '/')){
			      $endDay = preg_replace('/(\d+)\/(\d+)(.*)/i', '${1}', $end_date); // Strip spaces between tags
			      $endMonth = preg_replace('/(\d+)\/(\d+)(.*)/i', '${2}', $end_date); // Strip spaces between tags
			      $end_date  = $current_year . ((strlen(trim($endMonth))<2) ? "0" : "");
			      $end_date .= $endMonth . ((strlen(trim($endDay))<2) ? "0" : "") . $endDay  . "\r\n";
			      echo "DTEND;VALUE=DATE:" . $end_date;
			    }
			    else{
			      echo "DTEND;VALUE=DATE:" . $current_year;
			      echo ((strlen(trim($month_id))<2) ? "0" : "");
			      echo $month_id . ((strlen(trim($day_id))<2) ? "0" : "");
			      echo $day_id  . "\r\n";
			    }

			    echo "UID:h@" . md5($current_year . $month_id . $day_id . $title) . "@pucrs.br\r\n";
			    echo "ATTENDEE;CUTYPE=INDIVIDUAL;ROLE=REQ-PARTICIPANT;PARTSTAT=ACCEPTED;CN=PUCRS \r\n";
			    echo " Holidays;X-NUM-GUESTS=0:mailto:w3master@pucrs.br\r\n";
			    echo "CLASS:PUBLIC\r\n";
			    echo "SEQUENCE:1\r\n";
			    echo "STATUS:CONFIRMED\r\n";

			    echo "SUMMARY:" . stripAccents(utf8_decode(str_replace(",", "\,", $title))) . "\r\n";
			    echo "TRANSP:OPAQUE\r\n";
			    echo "END:VEVENT\r\n";
			  }
			}
  }
  echo "END:VCALENDAR\r\n";
}
?>
