<?php
function to_uint8_t($i){
	return pack('C',$i);
}
function from_uint8_t($i){
	//ord($i) , i know.
	$arr=unpack("Cuint8_t",$i);
	return $arr['uint8_t'];
}
function from_little_uint16_t($i){
	$arr=unpack('vuint16_t',$i);
	return $arr['uint16_t'];
}
function from_big_uint16_t($i){
	$arr=unpack('nuint16_t',$i);
	return $arr['nint16_t'];
}
function to_little_uint16_t($i){
	return pack('v',$i);
}
function to_big_uint16_t($i){
	return pack('n',$i);
}
function from_little_uint32_t($i){
	$arr=unpack('Vuint32_t',$i);
	return $arr['uint32_t'];
}
function from_big_uint32_t($i){
	$arr=unpack('Nuint32_t',$i);
	return $arr['uint32_t'];
}
function to_little_uint32_t($i){
	return pack('V',$i);
}
function to_big_uint32_t($i){
	return pack('N',$i);
}
function from_little_uint64_t($i){
	$arr=unpack('Puint64_t',$i);
	return $arr['uint64_t'];
}
function from_big_uint64_t($i){
	$arr=unpack('Juint64_t',$i);
	return $arr['uint64_t'];
}
function to_little_uint64_t($i){
	return pack('P',$i);
}
function to_big_uint64_t($i){
	return pack('J',$i);
}

/*splits up Nagle Algorithm combined data*/
function hhb_denagle($nagled_binary){
    $ret=array();
    $pos=0;
    $len=strlen($nagled_binary);
    while($len>0){
      if($len<2){
          throw new Exception('Invalid Nagle algorithm: at byte '.$pos.', length header is <2 bytes long!'); 
      }
      $sublen=from_little_uint16_t($nagled_binary[0].$nagled_binary[1]);
      $nagled_binary=substr($nagled_binary,2);
      $len-=2;
      if($len<$sublen){
          throw new Exception('Invalid Nagle algorithm: length header at byte '.$pos.' specify a length of '.$sublen.' bytes, but only '.$len.' bytes remain!');
      }
      $ret[]=substr($nagled_binary,0,$sublen);
      $nagled_binary=substr($nagled_binary,$sublen);
      $len-=$sublen;
      $pos+=$sublen+2;
    }
  return $ret;
}
