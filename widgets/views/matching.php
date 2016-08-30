<?php
/**
 * @author Semenov Alexander <semenov@skeeks.com>
 * @link http://skeeks.com/
 * @copyright 2010 SkeekS (СкикС)
 * @date 30.08.2016
 */
/* @var $this yii\web\View */
/* @var $widget \skeeks\cms\importCsvContent\widgets\MatchingInput */

//$dataColumns = $widget->model->getCsvColumnsData();
$widget = $this->context;

?>
<? if ($widget->model->getCsvColumnsData()) : ?>
    <? $all = $widget->model->getCsvColumnsData() ;?>
    <? $firstRow = $all[0] ;?>
    <table class="table table-striped table-bordered sx-table">
        <thead>
            <tr>
                <? foreach($firstRow as $value) : ?>
                    <th>
                        -
                    </th>
                <? endforeach; ?>
            </tr>
        </thead>
    <? foreach ($widget->model->getCsvColumnsData() as $row) : ?>
        <tr>
            <? foreach($row as $value) : ?>
                <td>
                    <?= $value; ?>
                </td>
            <? endforeach; ?>
        </tr>
    <? endforeach; ?>
    </table>
<? endif ;?>

