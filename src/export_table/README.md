# Export Table

## Tabellen-Export-Modul für Contao 4 

Mit dem Modul lassen sich Contao Tabellen im csv/xml-Format exportieren. Mit dem exportTable-Hook kann der Feldinhalt angepasst werden.
Erstelle dazu in system/modules ein neues Verzeichnis: aaa_export_table_hooks. Darin erstellst du in den entsprechenden Verzeichnissen die beiden php-Dateien. Anschliessend noch den autoload-Creatoor im Backend laufen lassen.
```php
<?php
// system/modules/aaa_export_table_hooks/config/config.php
$GLOBALS['TL_HOOKS']['exportTable'][] = array('Vendorname\ExportTableBundle\ContaoHooks\ExportTable', 'exportTableHook');

```

```php
<?php
// vendor/vendorname/export-table-bundle/src/ContaoHooks/ExportTable.php

namespace Vendorname\ExportTableBundle\ContaoHooks;

use Contao\Date;

/**
 * Class ExportTable
 * Copyright: 2020 Marko Cupic
 * @author Marko Cupic <m.cupic@gmx.ch>
 */
class ExportTable
{

    /**
     * @param $field
     * @param string $value
     * @param $table
     * @param $dataRecord
     * @param $dca
     * @return string
     */
    public static function exportTableHook($field, $value = '', $table, $dataRecord, $dca)
    {
        if ($table === 'tl_calendar_events')
        {
            if ($field === 'startDate' || $field === 'endDate' || $field === 'tstamp')
            {
                if ($value > 0)
                {
                    $value = Date::parse('d.m.Y', $value);
                }
            }
        }
        return $value;
    }
}

```
 

## ExportTable aus eigener Erweiterung heraus nutzen
Die ExportTable Klasse lässt sich auch sehr gut aus anderen Erweiterungen heraus nutzen. Unten siehst du ein Beispiel dazu.

```php
// Mitglieder exportieren
$opt = array();
$opt['arrSelectedFields'] = array('stateOfSubscription', 'hasParticipated', 'addedOn', 'eventName', 'firstname', 'lastname', 'sacMemberId', 'gender', 'street', 'postal', 'city', 'phone', 'email', 'dateOfBirth');
$opt['useLabelForHeadline'] = 'de';
$opt['exportType'] = 'csv';
$opt['arrFilter'][] = array('pid=?', \Contao\Input::get('id'));
$export = \Contao\System::getContainer()->get('Markocupic\ExportTable\Export\ExportTable');
$export->exportTable('tl_calendar_events_member', $opt);
```


Viel Spass mit Export Table! 
