<?php
namespace booosta\tools;

include_once 'punycode.incl.php';

use \booosta\Framework as b;
b::add_module_trait('base', 'tools\base');

trait base
{
  protected function get_opts_from_table($table, $showfield = 'name', $keyfield = 'id', $whereclause = null, $sort = 'a')
  {
    $this->init_db();

    if($whereclause == '') $whereclause = '0=0';
    if($sort && $sort != 'a' && $sort != 'd') $orderclause = "order by $sort"; else $orderclause = '';

    $rows = $this->DB->query_arrays("select `$keyfield`, `$showfield` from `$table` where $whereclause $orderclause");

    #b::debug($rows);
    $options = [];
    foreach($rows as $row) $options[$row[$keyfield]] = $row[$showfield];
  
    if($sort == 'a') asort($options);
    if($sort == 'd') arsort($options);
  
    return $options;
  }
  
  protected function get_opts_from_table0($table, $showfield = 'name', $keyfield = 'id', $whereclause = null, $sort = 'a')
  {
    $result = $this->get_opts_from_table($table, $showfield, $keyfield, $whereclause, $sort);
    return [0 => ''] + $result;
  }

  protected function get_opts_from_table_($table, $showfield = 'name', $keyfield = 'id', $whereclause = null, $sort = 'a')
  {
    $result = $this->get_opts_from_table($table, $showfield, $keyfield, $whereclause, $sort);
    return ['' => ''] + $result;
  }

  protected function get_act_opts_from_table($table, $ftable, $key, $fkfield = null, $fkfield2 = null, $showfield = 'name', $keyfield = 'id', $whereclause = null, $sort = 'a')
  {
    if($fkfield === null) $fkfield = $table;
    if($fkfield2 === null) $fkfield2 = $table;
  
    if($whereclause == '') $whereclause = '0=0';
    $rows = $this->DB->query_arrays("select a.`$keyfield`, a.`$showfield` from `$table` a where a.`$keyfield` in (select b.`$fkfield` from `$ftable` b where b.`$fkfield2`='$key') and $whereclause");
    $options = [];
    foreach($rows as $row) $options[$row[$keyfield]] = $row[$showfield];
  
    if($sort == 'a') asort($options);
    if($sort == 'd') arsort($options);
  
    return $options;
  }

  protected function real_dir($dir) { return $this->canonicalize(dirname($_SERVER['SCRIPT_FILENAME']) . $dir); }
  protected function real_basedir() { return $this->real_dir("/$this->base_dir"); }

  protected function canonicalize($address)
  {
    $prefix = substr($address, 0, 1) == '/' ? '/' : '';
    $address = explode('/', $address);
    $keys = array_keys($address, '..');

    foreach($keys AS $keypos => $key) array_splice($address, $key - ($keypos * 2 + 1), 2);

    $address = $prefix . implode('/', $address);
    #$address = str_replace('//', '/', $address);
    $address = preg_replace('/\/+/', '/', $address);
    return str_replace('./', '', $address);
  }

  protected function base_url($path = '')
  {
    $pathInfo = pathinfo($_SERVER['PHP_SELF']);
    $base_dir = $pathInfo['dirname'];

    $protocol = empty($_SERVER['HTTPS']) ? 'http' : 'https';
    $domain = $_SERVER['SERVER_NAME'];

    $port = $_SERVER['SERVER_PORT'];
    $disp_port = ($protocol == 'http' && $port == 80 || $protocol == 'https' && $port == 443) ? '' : ":$port";

    return "${protocol}://${domain}${disp_port}${base_url}${path}";
  }
  
  protected function generate_password($size = 8, $min_c = 0, $min_C = 0, $min_n = 0, $min_s = 0)
  {
    if($min_c + $min_C + $min_n > $size) return false;

    $c = ['a','b','c','d','e','f','g','h','k','m','n','p','q','r','s','t','u','v','w','x','y','z'];
    $C = ['A','B','C','D','E','F','G','H','K','M','N','P','Q','R','S','T','U','V','W','X','Y','Z'];
    $n = ['2','3','4','5','6','7','8','9'];
    $s = ['!','$','%','&','=','?','*','+','#',';','.',':','-','_','@'];

    if($min_s) $all = array_merge($c, $C, $n, $s);
    else $all = array_merge($c, $C, $n);

    shuffle($c);
    shuffle($C);
    shuffle($n);
    shuffle($s);
    shuffle($all);

    $pc = array_slice($c, 0, $min_c);
    $pC = array_slice($C, 0, $min_C);
    $pn = array_slice($n, 0, $min_n);
    $ps = array_slice($s, 0, $min_s);
    $pa = array_slice($all, 0, $size - $min_c - $min_C - $min_n - $min_s);

    $s = array_merge($pc, $pC, $pn, $ps, $pa);

    shuffle($s);
    $s = implode('', $s);
    return substr($s, 0, $size);
  }

  protected function timesel_get_TPL($name, $data = null)
  {
    if($data === null) $data = $this->get_data($name);
    $this->TPL["{$name}_time"] = substr($data, 11);
  }

  protected function timesel_set($name, $obj)
  {
    $obj->set($name, $this->VAR[$name] . ' ' . $this->VAR["{$name}_hour"] . ':' . $this->VAR["{$name}_minute"]); 
  }
  
  protected function replace_patterns($patterns, $text)
  {
    if(is_array($patterns))
      foreach($patterns as $key=>$replace) $text = str_replace('{' . $key . '}', $replace, $text);

    return $text;
  }
  
  protected function get_settings($table = 'settings')
  {
    $table = $this->DB->escape($table);
    return $this->DB->query_index_array("select attribute, value from `$table`");
  }

  protected function get_setting($attribute, $table = 'settings')
  {
    $table = $this->DB->escape($table);
    return $this->DB->query_value("select value from `$table` where attribute=?", $attribute);
  }

  protected function set_setting($attribute, $value, $table = 'settings')
  {
    $obj = $this->getDataobject($table, "attribute='$attribute'", true);
    $obj->set('attribute', $attribute);  // for new created records
    $obj->set('value', $value);
    $obj->save();
  }

  protected function save_setting($attribute, $value, $table = 'settings')
  {
    $this->set_setting($attribute, $value, $table);
  }

  protected function human_date($date = '_nil_', $time = false, $format = null, $month_names = false)
  {
    #\booosta\debug("date: $date");
    if($date === null) return null;
    if($date === '' || $date === '0000-00-00' || $date === '1970-01-01') return '';  // 1970 only for this project
    if($date == '_nil_') $date = date('Y-m-d');

    if($format === null) $format = $this->config('date_format') ?? 'm/d/Y';
    if(is_bool($format)) $format = $format ? 'd. m. Y' : 'd.m.Y';  // for backwards compatibility

    if($month_names):
      $map = ['Jan' => $this->t('January'), 
              'Feb' => $this->t('February'), 
              'Mar' => $this->t('March'), 
              'Apr' => $this->t('April'), 
              'May' => $this->t('May'), 
              'Jun' => $this->t('June'),
              'Jul' => $this->t('July'), 
              'Aug' => $this->t('August'), 
              'Sep' => $this->t('September'), 
              'Oct' => $this->t('October'), 
              'Nov' => $this->t('November'), 
              'Dec' => $this->t('December')];
              
      if(is_bool($format)) $format =  $format ? 'j. M Y' : 'J.M Y';;  // for backwards compatibility
    endif;

    $result = date($format, strtotime(str_replace(' ', '', $date)));
    if($month_names) $result = str_replace(array_keys($map), $map, $result);

    if($time == 'm') $format = ' H:i';
    elseif($time == 's' || $time === true) $format = ' H:i:s';
    else $format = null;

    if($format) $result .= date($format, strtotime(str_replace(' ', '', $date)));

    return $result;
  }

  protected function db_date($date, $time = false)
  {
    if($date == '' || $date == '0000-00-00') return null;

    if($time == 'm') $format = 'Y-m-d H:i';
    elseif($time == 's') $format = 'Y-m-d H:i:s';
    else $format = 'Y-m-d';

    if(is_numeric($date)) return date($format, $date);
    return date($format, strtotime(str_replace(' ', '', $date)));
  }

  protected function beautify_filename($name)
  {
    $name = strtr($name, ['ä' => 'ae', 'ö' => 'oe', 'ü' => 'ue', 'Ä' => 'Ae', 'Ö' => 'Oe', 'Ü' => 'Ue', 'ß' => 'ss']);
    $name = preg_replace('/[^A-Za-z0-9_]/', '_', $name);

    return $name;
  }
  
  protected function puny_encode($string)
  {
    if(!function_exists('mb_internal_encoding')) $this->raise_error('Missing dependency: mbstring');

    if(strstr($string, '@')):
      $result = [];
      $parts = explode('@', $string);
      foreach($parts as $part) $result[] = Punycode::encodeHostname($part);
      return implode('@', $result);
    endif;

    return Punycode::encodeHostname($string);
  }

  protected function puny_decode($punycode)
  {
    if(!function_exists('mb_internal_encoding')) $this->raise_error('Missing dependency: mbstring');

    if(strstr($punycode, '@')):
      $result = [];
      $parts = explode('@', $punycode);
      foreach($parts as $part) $result[] = Punycode::decodeHostname($part);
      return implode('@', $result);
    endif;

    return Punycode::decodeHostname($punycode);
  }

  protected function is_puny_domain($domain)
  {
    return $this->puny_encode($domain) == $domain;
  }

  protected function array_combine($a, $b)
  {
    $size = min(count($a), count($b));
    $a = array_slice($a, 0, $size);
    $b = array_slice($b, 0, $size);

    return array_combine($a, $b);
  }   

  protected function download_file($filename, $ct = 'application/octet-stream', $disp = 'attachment', $no_output = true, $showfilename = null)
  {
    header("Content-type: $ct");
    header('Content-Length: ' . filesize($filename));
    $showfilename = $showfilename ?? basename($filename);
    header("Content-Disposition: $disp; filename=$showfilename");
    readfile($filename);

    $this->no_output = $no_output;
  }

  protected function math_eval($expression, $vars = null)
  {
    if (function_exists('math_eval')):
      try { return math_eval($expression, $vars); } 
      catch(\Exception $e) { return null; }
    endif;

    return null;
  }

  protected function remove_nl($text, $br = false)
  {
    if($br) $text = nl2br($text);
    return str_replace(["\r\n", "\n", "\r"], '', $text);
  }
}

#if(is_readable('lib/modules/tools/extratools.incl.php')) include_once('lib/modules/tools/extratools.incl.php');
