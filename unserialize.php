<?php

// import .xml file
$file = 'noticias-prceu.xml';
$wordpress_xml = file_get_contents($file);


// find all the posts in the xml file
preg_match_all('/<item>(.*?)<\/item>/s', $wordpress_xml, $posts);
//$posts = $xml->xpath('//item');

//print_r($posts[1]);

// find the postmeta tags that contain the key locais
$locais[] = array();
preg_match_all('/<wp:postmeta>(.*?)<\/wp:postmeta>/s', $wordpress_xml, $postmeta);
if (count($postmeta[1]) > 0) {
    foreach ($postmeta[1] as $meta) {
        preg_match('/<wp:meta_key><!\[CDATA\[(.*?)\]\]><\/wp:meta_key>/', $meta, $key);
        preg_match('/<wp:meta_value><!\[CDATA\[(.*?)\]\]><\/wp:meta_value>/', $meta, $value);
        if ($key[1] == 'locais') {
            $locais[] = $value[1];
        }
    }
}

// unserialize the data except the 'dias' array, formatting the data to the new standard
$i = 0;
$unserialized[] = array();
foreach ($locais as $local) {
    if ($i == 0) {
        $i++;
        continue;
    }
    $locais_unserialized[$i] = unserialize($local);
    if ( isset($locais_unserialized[$i])){
        for ( $j=0; isset($locais_unserialized[$i][$j]); $j++) {
            if ( !is_null($locais_unserialized[$i][$j]) ){
                $new = array();
                $old = $locais_unserialized[$i][$j];
                $new["locais_".$j."_nome_do_local"] = isset($old['local']) ? $old['local'] : "";
                $new["locais_".$j."_endereco"]      = isset($old['endereco']) ? $old['endereco'] : "";
                $new["locais_".$j."_cidade"]        = isset($old['cidade']) ? $old['cidade'] : "";
                $new["locais_".$j."_cep"]           = isset($old['cep']) ? $old['cep'] : "";
                $new["locais_".$j."_telefone_1"]    = isset($old['telefone_1']) ? $old['telefone_1'] : "";
                $new["locais_".$j."_telefone_2"]    = isset($old['telefone_2']) ? $old['telefone_2'] : "";
                $new["locais_".$j."_email"]         = isset($old['email']) ? $old['email'] : "";
                $new["locais_".$j."_observacoes"]   = isset($old['observacoes']) ? $old['observacoes'] : "";
                for ( $k=0; isset($old['horarios'][$k]['dias']) and !is_null($old['horarios'][$k]['dias']); $k++) {
                    $quantidade_locais = count($old['horarios'][$k]['dias']);
                    $keys_locais = array_keys($old['horarios'][$k]['dias']);
                    $locais_serialized = "a:$quantidade_locais{";
                    for ($a=0; $a < $quantidade_locais; $a++){
                        $locais_serialized .= "i:$a;s:3:\"$keys_locais[$a]\";";
                    }
                    $locais_serialized .= "}";
                    $new["locais_".$j."_dias_da_semana__horario_".$k."_dias_da_semana"] = $locais_serialized;
                    $new["locais_".$j."_dias_da_semana__horario_".$k."_horario_de_inicio"] = $old['horarios'][$k]['hora_i'];
                    $new["locais_".$j."_dias_da_semana__horario_".$k."_horario_de_termino"] = $old['horarios'][$k]['hora_f'];
                    $unserialized[$i] = $new;
                    unset($locais_serialized);
                }
            }
        }
    }
    $i++;
}

unset($locais_unserialized);
//print_r($unserialized);
class MySimpleXMLElement extends SimpleXMLElement{
    public function addChildWithCData($name , $value, $namespace) {
        $new = parent::addChild($name, null, $namespace);
        $base = dom_import_simplexml($new);
        $docOwner = $base->ownerDocument;
        $base->appendChild($docOwner->createCDATASection($value));
    }
}
$xml = new MySimpleXMLElement($wordpress_xml);

$namespaces = $xml->getDocNamespaces();
$namespace_wp = $namespaces['wp'];


// substitute the data on the xml file
foreach ($xml->channel->item as $item) {
    foreach ($item->children($namespace_wp) as $index => $postmeta){
        foreach ($postmeta->children($namespace_wp) as $meta) {
            if ($meta->getName() == 'meta_key' && $meta->__toString() == 'locais'){
                unset($postmeta[0]);
                break 2; // Break out of the inner and outer loops
            }
        }
    }
}
$i = 1;
foreach ($xml->channel->item as $item) {
    $current_data = isset($unserialized[$i]) ? $unserialized[$i] : [];
    if (!empty($current_data)) {
        $keys = array_keys($current_data);
        foreach ( $keys as $key ) {
            $postmeta = $item->addChild("wp:postmeta", null, $namespace_wp);
            $postmeta->addChildWithCData('wp:meta_key', $key, $namespace_wp);
            $postmeta->addChildWithCData('wp:meta_value', $unserialized[$i][$key], $namespace_wp);
        }
    }
    $i++;
}

//echo $xml->asXML();
$xml->asXML('wordpress_modified_import.xml');

