<?php
/* vim: set expandtab sw=4 ts=4 sts=4: */
/**
 *
 * @package PhpMyAdmin-Designer
 */

/**
 *
 */
require_once './libraries/common.inc.php';

PMA_Response::getInstance()->disable();
$common_functions = PMA_CommonFunctions::getInstance();

require_once 'libraries/pmd_common.php';
extract($_POST, EXTR_SKIP);
extract($_GET, EXTR_SKIP);
$die_save_pos = 0;
require_once 'pmd_save_pos.php';
list($DB1, $T1) = explode(".", $T1);
list($DB2, $T2) = explode(".", $T2);

$tables = PMA_DBI_get_tables_full($db, $T1);
$type_T1 = strtoupper($tables[$T1]['ENGINE']);
$tables = PMA_DBI_get_tables_full($db, $T2);
$type_T2 = strtoupper($tables[$T2]['ENGINE']);

$try_to_delete_internal_relation = false;

if ($common_functions->isForeignKeySupported($type_T1)
    && $common_functions->isForeignKeySupported($type_T2)
    && $type_T1 == $type_T2
) {
    // InnoDB
    $existrel_foreign = PMA_getForeigners($DB2, $T2, '', 'foreign');

    if (isset($existrel_foreign[$F2]['constraint'])) {
        $upd_query  = 'ALTER TABLE ' . $common_functions->backquote($DB2)
                  . '.' . $common_functions->backquote($T2) . ' DROP FOREIGN KEY '
                  . $common_functions->backquote($existrel_foreign[$F2]['constraint'])
                  . ';';
        $upd_rs     = PMA_DBI_query($upd_query);
    } else {
        // there can be an internal relation even if InnoDB
        $try_to_delete_internal_relation = true;
    }
} else {
    $try_to_delete_internal_relation = true;
}
if ($try_to_delete_internal_relation) {
    // internal relations
    PMA_queryAsControlUser(
        'DELETE FROM '
        . $common_functions->backquote($GLOBALS['cfgRelation']['db']) . '.'
        . $cfg['Server']['relation'].' WHERE '
        . 'master_db = \'' . $common_functions->sqlAddSlashes($DB2) . '\''
        . ' AND master_table = \'' . $common_functions->sqlAddSlashes($T2) . '\''
        . ' AND master_field = \'' . $common_functions->sqlAddSlashes($F2) . '\''
        . ' AND foreign_db = \'' . $common_functions->sqlAddSlashes($DB1) . '\''
        . ' AND foreign_table = \'' . $common_functions->sqlAddSlashes($T1) . '\''
        . ' AND foreign_field = \'' . $common_functions->sqlAddSlashes($F1) . '\'',
        false,
        PMA_DBI_QUERY_STORE
    );
}
PMD_return_upd(1, __('Relation deleted'));

function PMD_return_upd($b, $ret)
{
    global $K;
    header("Content-Type: text/xml; charset=utf-8");
    header("Cache-Control: no-cache");
    die('<root act="relation_upd" return="'.$ret.'" b="'.$b.'" K="'.$K.'"></root>');
}
?>
