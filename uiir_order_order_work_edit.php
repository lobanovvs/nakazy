<?php
use Bitrix\Main\Loader;
use Bitrix\Main\Localization\Loc;
use Bitrix\Main\Context;
use Bitrix\Main\UserTable;
use Bitrix\Main\Entity;
use Bitrix\Main\UI\Extension;
use Bitrix\Main\Type\Date;
use Uiir\Order;

require_once($_SERVER["DOCUMENT_ROOT"] . "/bitrix/modules/main/include/prolog_admin_before.php");

global $APPLICATION;

if ($APPLICATION->GetUserRight("uiir.order") <= "D")
	$APPLICATION->AuthForm("Access denied");

Loader::includeModule("uiir.order");
Loc::loadMessages(__FILE__);
Extension::load("ui.alerts");
$id = Context::getCurrent() -> getRequest() -> get("id");

$aTabs = array(
	array(
		"DIV" => "edit1",
		"TAB" => Loc::getMessage("ORDER_WORK_TABS_TAB1_NAME"),
		"ICON" => "main_user_edit",
		"TITLE" => Loc::getMessage("ORDER_WORK_TABS_TAB1_TITLE")
	),
	array(
		"DIV" => "edit2",
		"TAB" => Loc::getMessage("ORDER_WORK_TABS_TAB2_NAME"),
		"ICON" => "main_user_edit",
		"TITLE" => Loc::getMessage("ORDER_WORK_TABS_TAB2_TITLE")
	),
	array(
		"DIV" => "edit3",
		"TAB" => Loc::getMessage("ORDER_WORK_TABS_TAB3_NAME"),
		"ICON" => "main_user_edit",
		"TITLE" => Loc::getMessage("ORDER_WORK_TABS_TAB3_TITLE")
	)
);

$tabControl = new CAdminTabControl("tabControl", $aTabs);
$messageError = [];
$bVarsFromForm = false;

if ($id > 0)
{
	$order = Order\OrdersTable::customGetList(array(
		"select" => array(
			"id", 
			"order_group",
			"person_id",
			"person",
			"message",
			"address", 
			"region",
			"state",
			"limit_date",
			"limit_period_date",
			"industry",
			"level",
			"officer",
			"history",
			"organization_primary",
			"reports",
			"type_nakaz"
		),
		'filter' => array('=id' => $id)
	)) -> fetchObject();
	$orgs = [];
	$other_spa = [];
	$orgsPId = [];
	$orgsId = [];
	if (!$order)
	{
		$id = 0;
		$messageError[] = Loc::getMessage('ORDER_WORK_ACCESS_DENIED');
	}
	else
	{
		$reference = Order\AccessTable::getList(
			array(
				'select' => array('id', 'org_id', 'order_id', 'organization', 'type'), 
				'filter' => array('=order_id' => $order -> getId())
			)
		) -> fetchCollection();
		foreach ($reference as $org)
		{
			if ($order -> getOrganizationPrimary() -> getOrgId() == $org -> getOrganization() -> getId())
			{
				$orgs[] = '<strong>' . $org -> getOrganization() -> getValue() . '</strong>';
				$orgsPId[] = $org -> getOrganization() -> getId();
			}
			else
			{
				$other_spa[] = $org -> getOrganization() -> getValue();
				$orgsId[] = $org -> getOrganization() -> getId();
			}
		}
	}	
}
/* Изменен 19.02.2021 9:24 Лобанов */
if($REQUEST_METHOD == "POST" && $_POST["change_spa_select"] && $_POST["change_spa_text"] && $_POST["id"] && check_bitrix_sessid())
{
	$check_order = Order\ChangeOrdersTable::getCount(array('=order_id' => $_POST["id"], '=status_id' => 'NULL'));
	if($check_order == 0){
		$newChangeOrders = Order\ChangeOrdersTable::createObject();
		$newChangeOrders -> setOrderId($_POST["id"]);
		$newChangeOrders -> setUserAdd($USER->getId());
		$newChangeOrders -> setChangeField('primary_org_id');
		$newChangeOrders -> setOldValue($order->getOrganizationPrimary()->getOrgId());
		$newChangeOrders -> setNewValue($_POST["change_spa_select"]);
		$newChangeOrders -> setChangeDesc($_POST["change_spa_text"]);
		if ($newChangeOrders -> save()){
			LocalRedirect("uiir_order_order_in_new_list.php");
		}else{
			$messageError[] = Loc::getMessage("ORDER_WORK_EDIT_ERROR");
			$bVarsFromForm = true;
		}
	}else{
		$messageError[] = Loc::getMessage("ORDER_WORK_EDIT_CHANGE_SPA_ERROR_IN_MOD");
		$bVarsFromForm = true;
	}
};

if($REQUEST_METHOD == "POST" && ($addOfficerSave!="") && check_bitrix_sessid())
{
	if ($addOfficerString)
	{
		$newState = Order\StatesTable::getList(array('filter' => array('=alias' => 'work'), 'limit' => 1)) -> fetchObject();
		$order -> setState($newState);
		$order -> setOfficer($addOfficerString);
		$newOperation = Order\OperationsTable::getList(array('filter' => array('=alias' => 'assigned'), 'limit' => 1)) -> fetchObject();
		$newHistory = Order\HistoryTable::createObject();
		$newHistory -> setUserId($USER -> getId());
		$newHistory -> setOld($order -> remindActualOfficer());
		$newHistory -> setNew($order -> getOfficer());
		$newHistory -> setOperation($newOperation);
		$newHistory -> setOrder($order);
		if ($order -> save())
		{
			$newHistory -> save();
			LocalRedirect("uiir_order_order_in_new_list.php");	
		}
		else
		{
			$messageError[] = Loc::getMessage("ORDER_WORK_EDIT_ERROR");	
			$bVarsFromForm = true;	
		}
	}
	else
	{
		$messageError[] = Loc::getMessage("ORDER_WORK_EDIT_ADD_OFFICER_ERROR");
		$bVarsFromForm = true;
	}
};

if($REQUEST_METHOD == "POST" && ($iSeeSave!="") && check_bitrix_sessid())
{
	$stateWork= Order\StatesTable::getRow(array('filter' => array('=alias' => 'work')));
	$newState = Order\StatesTable::wakeUpObject($stateWork['id']);
	$opResolution = Order\OperationsTable::getRow(array('filter' => array('=alias' => 'seeresolution')));
	$newOp = Order\OperationsTable::getById($opResolution['id']) -> fetchObject();
	$order -> setState($newState);
	$res = $order -> save();
	$history = Order\HistoryTable::add(array(
		'order_id' => $id,
		'operation_id' => $opResolution['id'],
		'user_id' => $USER -> getId()
	));
	if (!$res || $history -> LAST_ERROR)
	{
		$messageError[] = Loc::getMessage("ORDER_WORK_EDIT_ERROR");	
		$bVarsFromForm = true;	
	}
	else
		LocalRedirect("uiir_order_order_in_done_list.php");
};

if($REQUEST_METHOD == "POST" && ($sendReportSave!="") && check_bitrix_sessid())
{
	if ($sendReportText)
	{
		$newState = Order\StatesTable::getList(array('filter' => array('=alias' => 'mod'), 'limit' => 1)) -> fetchObject();
		$order -> setState($newState);

		$newOperationReport = Order\OperationsTable::getList(array('filter' => array('=alias' => 'sent'), 'limit' => 1)) -> fetchObject();
		$newHistoryReport = Order\HistoryTable::createObject();
		$newHistoryReport -> setUserId($USER -> getId());
		$newHistoryReport -> setOperation($newOperationReport);
		$newHistoryReport -> setOrder($order);

		$newReport = Order\ReportsTable::createObject();
		$newReport -> setUserId($USER -> getId());
		$newReport -> setOrder($order);
		$newReport -> setMessage($sendReportText);

		$newHistoryLevel = false;
		if ($sendReportLevel != $order -> getLevel() -> getId())
		{
			if ($newLevel = Order\LevelsTable::getByPrimary(intval($sendReportLevel)) -> fetchObject())
			{
				$newOperationLevel = Order\OperationsTable::getList(array('filter' => array('=alias' => 'limitedit'), 'limit' => 1)) -> fetchObject();
				$newHistoryLevel = Order\HistoryTable::createObject();
				$newHistoryLevel -> setUserId($USER -> getId());
				$newHistoryLevel -> setOperation($newOperation);
				$newHistoryLevel -> setOrder($order);
				$newHistoryLevel -> setOld($order -> getLevel() -> getValue());
				$newHistoryLevel -> setNew($newLevel -> getValue());
				$order -> setLevel($newLevel);
			}
		}
		/*if (intval($sendReportBudgetPlan))
		{
			$opSent = Order\OperationsTable::getRow(array('filter' => array('=alias' => 'bpedit')));
			$newOp = Order\OperationsTable::getById($opSent['id']) -> fetchObject();
			$history = Order\HistoryTable::add(array(
				'order_id' => $id,
				'operation_id' => $opSent['id'],
				'user_id' => $USER -> getId(),
				'old' => $order -> getBudgetPlan(),
				'new' => intval($sendReportBudgetPlan)
			));
			$order -> setBudgetPlan(intval($sendReportBudgetPlan));
		}

		if (intval($sendReportBudgetFact))
		{
			$opSent = Order\OperationsTable::getRow(array('filter' => array('=alias' => 'bfedit')));
			$newOp = Order\OperationsTable::getById($opSent['id']) -> fetchObject();
			$history = Order\HistoryTable::add(array(
				'order_id' => $id,
				'operation_id' => $opSent['id'],
				'user_id' => $USER -> getId(),
				'old' => $order -> getBudgetFact(),
				'new' => intval($sendReportBudgetFact)
			));
			$order -> setBudgetFact(intval($sendReportBudgetFact));
		}*/
		if ($order -> save())
		{
			$newHistoryReport -> save();
			$newReport -> save();
			if ($newHistoryLevel)
				$newHistoryLevel -> save();
			LocalRedirect("uiir_order_order_work_list.php");	
		}
		else
		{
			$messageError[] = Loc::getMessage("ORDER_WORK_EDIT_ERROR");	
			$bVarsFromForm = true;	
		}
	}
	else
	{
		$messageError[] = Loc::getMessage("ORDER_WORK_EDIT_ADD_REPORT_ERROR");
		$bVarsFromForm = true;
	}
};

if($REQUEST_METHOD == "POST" && ($modSave!="") && check_bitrix_sessid())
{
	if (!$sendResolutionText)
	{
		$messageError[] = Loc::getMessage("ORDER_WORK_EDIT_MOD_ERROR");
		$bVarsFromForm = true;
	}
	if ((!$sendResolutionLimitDate) || (!MakeTimeStamp($sendResolutionLimitDate, 'DD.MM.YYYY')))
	{
		$messageError[] = Loc::getMessage("ORDER_WORK_EDIT_MOD_ERROR_LIMIT_DATE");
		$bVarsFromForm = true;
	}
	if ((!$sendResolutionLimitPeriodDate) || (!MakeTimeStamp($sendResolutionLimitPeriodDate, 'DD.MM.YYYY')))
	{
		$messageError[] = Loc::getMessage("ORDER_WORK_EDIT_MOD_ERROR_LIMIT_PERIOD_DATE");
		$bVarsFromForm = true;
	}
	elseif ((MakeTimeStamp($sendResolutionLimitPeriodDate . ' 23:59:59', 'DD.MM.YYYY HH:MI:SS')) <= time())
	{
		$messageError[] = Loc::getMessage("ORDER_WORK_EDIT_MOD_ERROR_LIMIT_PERIOD_DATE_OLD");
		$bVarsFromForm = true;
	}

	if (!count($messageError))
	{
		$stateChecked = Order\StatesTable::getRow(array('filter' => array('=alias' => 'checked')));
		$newState = Order\StatesTable::wakeUpObject($stateChecked['id']);
		$order -> setState($newState);
		$order -> save();

		$lastReport = Order\ReportsTable::getList(
			array(
				"select" => array("*"),
				"filter" => array("order_id" => $order -> getId()),
				"order" => array("id" => "DESC"),
				"limit" => 1
			)
		) -> fetchObject();

		$lastReport -> setResolution($sendResolutionText);
		$lastReport -> setTimestampResolve(new \Bitrix\Main\Type\DateTime());
		$lastReport -> setUserResolveId($USER -> getId());
		$lastReport -> save();

		$opSent = Order\OperationsTable::getRow(array('filter' => array('=alias' => 'resolution')));
		$newOp = Order\OperationsTable::getById($opSent['id']) -> fetchObject();
		$history = Order\HistoryTable::add(array(
			'order_id' => $id,
			'operation_id' => $opSent['id'],
			'user_id' => $USER -> getId(),
			'new' => $sendResolutionText
		));

		if ($sendResolutionLimitDate != $order -> getLimitDate())
		{
			$opSent = Order\OperationsTable::getRow(array('filter' => array('=alias' => 'dateedit')));
			$history = Order\HistoryTable::add(array(
				'order_id' => $id,
				'operation_id' => $opSent['id'],
				'user_id' => $USER -> getId(),
				'old' => $order -> getLimitDate(),
				'new' => $sendResolutionLimitDate
			));
			$order -> setLimitDate(new Date($sendResolutionLimitDate));
			$order -> save();		
		}

		if ($sendResolutionLimitPeriodDate != $order -> getLimitPeriodDate())
		{
			$opSent = Order\OperationsTable::getRow(array('filter' => array('=alias' => 'gapdateedit')));
			$newOp = Order\OperationsTable::getById($opSent['id']) -> fetchObject();
			$history = Order\HistoryTable::add(array(
				'order_id' => $id,
				'operation_id' => $opSent['id'],
				'user_id' => $USER -> getId(),
				'old' => $order -> getLimitPeriodDate(),
				'new' => $sendResolutionLimitPeriodDate
			));
			$order -> setLimitPeriodDate(new Date($sendResolutionLimitPeriodDate));
			$order -> save();		
		}
		if ($sendResolutionOfficer != $order -> getOfficer())
		{
			$opSent = Order\OperationsTable::getRow(array('filter' => array('=alias' => 'changeofficer')));
			$newOp = Order\OperationsTable::getById($opSent['id']) -> fetchObject();
			$history = Order\HistoryTable::add(array(
				'order_id' => $id,
				'operation_id' => $opSent['id'],
				'user_id' => $USER -> getId(),
				'old' => $order -> getOfficer(),
				'new' => $sendResolutionOfficer
			));
			$order -> setOfficer($sendResolutionOfficer);
			$order -> save();
		}
		
		if (!in_array(intval($sendResolutionOrgP), $orgsPId))
		{
			$opSent = Order\OperationsTable::getRow(array('filter' => array('=alias' => 'assigned')));
			$newOp = Order\OperationsTable::getById($opSent['id']) -> fetchObject();
			$history = Order\HistoryTable::add(array(
				'order_id' => $id,
				'operation_id' => $opSent['id'],
				'user_id' => $USER -> getId(),
				'old' => Order\OrganizationsTable::getById($orgsPId[0]) -> fetchObject() -> getValue(),
				'new' => Order\OrganizationsTable::getById($sendResolutionOrgP) -> fetchObject() -> getValue()
			));
			$accessP = Order\AccessTable::getList(array(
				'filter' => array(
					'order_id' => $id,
					'org_id' => $orgsPId[0],
					'type' => 'P'
				),
				'limit' => 1
			)) -> fetchObject();
			$accessP -> setOrgId($sendResolutionOrgP);
			$accessP -> save();
		}

		foreach($sendResolutionOrgS as $i => $org)
		{
			$sendResolutionOrgS[$i] = intval($org);
		};
		sort($sendResolutionOrgS);
		sort($orgsId);
		var_dump($sendResolutionOrgS);
		var_dump($orgsId);
		if ($sendResolutionOrgS != $orgsId)
		{
			$opSent = Order\OperationsTable::getRow(array('filter' => array('=alias' => 'secassigned')));
			$newOp = Order\OperationsTable::getById($opSent['id']) -> fetchObject();
			$history = Order\HistoryTable::add(array(
				'order_id' => $id,
				'operation_id' => $opSent['id'],
				'user_id' => $USER -> getId(),
				'old' => ($orgsId) ? implode('; ', Order\OrganizationsTable::getList(array('filter' => array('=id' => $orgsId))) -> fetchCollection() -> getValueList()) : '',
				'new' => ($sendResolutionOrgS) ? implode('; ', Order\OrganizationsTable::getList(array('filter' => array('=id' => $sendResolutionOrgS))) -> fetchCollection() -> getValueList()): ''
			));
			foreach($orgsId as $org)
			{
				Order\AccessTable::getList(array(
					'filter' => array(
						'order_id' => $id,
						'org_id' => $org,
						'type' => 'S'
					),
					'limit' => 1
				)) -> fetchObject() -> delete();
			}
			foreach($sendResolutionOrgS as $org)
			{
				Order\AccessTable::add(array(
					'order_id' => $id,
					'org_id' => $org,
					'type' => 'S'
				));
			}
		};

		LocalRedirect("uiir_order_order_waiting_list.php");
	}
};

if($REQUEST_METHOD == "POST" && ($modDoneSave!="") && check_bitrix_sessid())
{
	$stateDone = Order\StatesTable::getRow(array('filter' => array('=alias' => 'done')));
	$newState = Order\StatesTable::wakeUpObject($stateDone['id']);
	$order -> setState($newState);
	$order -> save();

	$opSent = Order\OperationsTable::getRow(array('filter' => array('=alias' => 'done')));
	$history = Order\HistoryTable::add(array(
		'order_id' => $id,
		'operation_id' => $opSent['id'],
		'user_id' => $USER -> getId(),
	));

	$lastReport = Order\ReportsTable::getList(
		array(
			"select" => array("*"),
			"filter" => array("order_id" => $order -> getId()),
			"order" => array("id" => "DESC"),
			"limit" => 1
		)
	) -> fetchObject();
	$lastReport -> setResolution($stateDone["value"]);
	$lastReport -> setTimestampResolve(new \Bitrix\Main\Type\Date());
	$lastReport -> setUserResolveId($USER -> getId());
	$lastReport -> save();

	LocalRedirect("uiir_order_order_waiting_list.php");
};

if($REQUEST_METHOD == "POST" && ($modRejSave!="") && check_bitrix_sessid())
{
	$stateRejected = Order\StatesTable::getRow(array('filter' => array('=alias' => 'rejected')));
	$newState = Order\StatesTable::wakeUpObject($stateRejected['id']);
	$order -> setState($newState);
	$order -> save();

	$opSent = Order\OperationsTable::getRow(array('filter' => array('=alias' => 'rejected')));
	$history = Order\HistoryTable::add(array(
		'order_id' => $id,
		'operation_id' => $opSent['id'],
		'user_id' => $USER -> getId(),
	));

	$lastReport = Order\ReportsTable::getList(
		array(
			"select" => array("*"),
			"filter" => array("order_id" => $order -> getId()),
			"order" => array("id" => "DESC"),
			"limit" => 1
		)
	) -> fetchObject();
	$lastReport -> setResolution($stateRejected["value"]);
	$lastReport -> setTimestampResolve(new \Bitrix\Main\Type\Date());
	$lastReport -> setUserResolveId($USER -> getId());
	$lastReport -> save();

	LocalRedirect("uiir_order_order_waiting_list.php");
};

CJSCore::Init(array('jquery'));
$APPLICATION->AddHeadScript("/bitrix/js/uiir.order/form.js");
$APPLICATION->SetAdditionalCSS("/bitrix/themes/uiir.order/form.css");
$APPLICATION->SetAdditionalCSS("/bitrix/themes/uiir.order/table.css");
$docUrl = '';
if ($id)
	switch ($order -> getState() -> getAlias())
	{
		case 'created':
			$APPLICATION->SetTitle($id > 0 ? Loc::getMessage("ORDER_WORK_NEW_TITLE") . $id : "");
			$docUrl = "uiir_order_order_in_new_report.php?id=" . $id;
			break;
		case 'checked':
			$APPLICATION->SetTitle($id > 0 ? Loc::getMessage("ORDER_WORK_CHECKED_TITLE") . $id : "");
			$docUrl = "uiir_order_order_in_done_report.php?id=" . $id;
			break;
		case 'mod':
			$APPLICATION->SetTitle($id > 0 ? Loc::getMessage("ORDER_WORK_MOD_TITLE") . $id : "");
			$docUrl = "uiir_order_order_waiting_report.php?id=" . $id;
			break;
		case 'done':
			$APPLICATION->SetTitle($id > 0 ? Loc::getMessage("ORDER_WORK_DONE_TITLE") . $id : "");
			$docUrl = "uiir_order_order_done_report.php?id=" . $id;
			break;
		case 'rejected':
			$APPLICATION->SetTitle($id > 0 ? Loc::getMessage("ORDER_WORK_REJ_TITLE") . $id : "");
			$docUrl = "uiir_order_order_rejected_report.php?id=" . $id;
			break;
		default:
			$APPLICATION->SetTitle($id > 0 ? Loc::getMessage("ORDER_WORK_TITLE") . $id : "");
			$docUrl = "uiir_order_order_work_report.php?id=" . $id;
			break;
	}

require($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/prolog_admin_after.php");

if ($messageError)
	echo '<div class="ui-alert ui-alert-danger"><span class="ui-alert-message">' . implode($messageError, '<br>') . '</span></div>';

if ($docUrl)
{
	$aMenu = array(
	array(
		"TEXT" => Loc::getMessage("ORDER_WORK_EDIT_DOC"),
		"TITLE" => Loc::getMessage("ORDER_WORK_EDIT_DOC"),
		"LINK" => $docUrl
	)
	);

	$context = new CAdminContextMenu($aMenu);
	$context -> Show();
}
?>

<?
if ($id)
{
	$lastReport = Order\ReportsTable::getList(
		array(
			"select" => array("*"),
			"filter" => array("order_id" => $order -> getId()),
			"order" => array("id" => "DESC"),
			"limit" => 1
		)
	) -> fetchObject();
	if (($order -> getState() -> getAlias() == 'mod' || $order -> getState() -> getAlias() == 'checked') && $lastReport)
	{
		$fio = array();
		$rsUser = UserTable::getById($lastReport -> getUserId()) -> fetchObject();
		if ($rsUser)
		{
			$fio[] = $rsUser -> getLastName();
			$fio[] = $rsUser -> getName();
			$fio[] = $rsUser -> getSecondName();
		}
		if ($order -> getState() -> getAlias() == 'checked')
			echo '<div class="ui-alert ui-alert-success"><span class="ui-alert-message"><strong>' . Loc::getMessage("ORDER_WORK_EDIT_LAST_REPORT_") . '</strong><br>';
		else
			echo '<div class="ui-alert ui-alert-success"><span class="ui-alert-message"><strong>' . Loc::getMessage("ORDER_WORK_EDIT_LAST_REPORT") . '</strong><br>';
		echo $lastReport -> getMessage();
		echo "<br>";
		echo Loc::getMessage("ORDER_WORK_EDIT_REPORT_USER") . ": " . $lastReport -> getTimestamp() . ", " . implode($fio, " ");
		if ($lastReport -> getTimestampResolve())
		{
			$fio_resolve = array();
			$rsUser = UserTable::getById($lastReport -> getUserResolveId()) -> fetchObject();
			if ($rsUser)
			{
				$fio_resolve[] = $rsUser -> getLastName();
				$fio_resolve[] = $rsUser -> getName();
				$fio_resolve[] = $rsUser -> getSecondName();
			}
			echo "<br><br>";
			echo '<strong>' . Loc::getMessage("ORDER_WORK_EDIT_REPORT_RESOLVE_USER") . ": " . $lastReport -> getTimestampResolve() . ", " . implode($fio_resolve, " ");
			echo "<br>";
			echo Loc::getMessage("ORDER_WORK_EDIT_REPORT_RESOLUTION") . ": " . $lastReport -> getResolution() . '</strong>';
		}
		echo "</span></div>";
	}
	if ($order -> getState() -> getAlias() == 'work')
	{
		$today = new Date();
		if ((($order -> getLimitPeriodDate() -> getTimestamp()) < ($today -> getTimestamp())))
		{
			echo '<div class="ui-alert ui-alert-danger"><span class="ui-alert-message">' . Loc::getMessage("ORDER_WORK_EDIT_EXPIRED_REPORT") . '<span></div>';
		}
		else
		{
			echo '<div class="ui-alert ui-alert-warning"><span class="ui-alert-message">' . Loc::getMessage("ORDER_WORK_EDIT_WAITING_REPORT") . $order -> getLimitPeriodDate() . '<span></div>';
		}
	}
	?>

	<form method="post" action="" enctype=multipart/form-data" name="post_form">
		<?
		echo bitrix_sessid_post();
		$tabControl->Begin();

		$tabControl->BeginNextTab();
		?>
		<tr>
			<td style="width: 30%" class="adm-detail-valign-top adm-detail-content-cell-l"><?=Loc::getMessage("ORDER_WORK_EDIT_ORDER")?></td>
			<td style="width: 70%" class="adm-detail-content-cell-r">
				<?=$order -> getOrderGroup() -> getMessage()?>
				<?php
				$cnt = Order\AssignmentsTable::customGetList(array(
					'count_total' => true, 
					'filter' => array('=order_id' => $order -> getOrderGroup() -> getId()),
				)) -> getCount();
				?>
				<br>
				<span style="font-style: italic;">
				<?=Loc::getMessage("ORDER_WORK_EDIT_ORDER_COUNT") . $cnt . '. <a target="_blank" href="uiir_order_order_group_work_list.php?group_id=' . $order -> getOrderGroup() -> getId() . '">' . Loc::getMessage("ORDER_WORK_EDIT_PERSON_LINK") . '</a>'?>
				</span>
			</td>
		</tr>
		<tr>
			<td class="adm-detail-valign-top adm-detail-content-cell-l"><?=Loc::getMessage("ORDER_WORK_EDIT_MESSAGE")?></td>
			<td class="adm-detail-content-cell-r">
				<?
				$mes_arr = explode(';',$order -> getMessage());
				$mes_arr = array_unique($mes_arr);
				if(count($mes_arr) > 1){
				?>
				<div class="accordion_block">
					<span class="accordion_block_btn accordion_block_btn_show"><?=Loc::getMessage("ORDER_WORK_EDIT_HIDE")?></span>
					<span class="accordion_block_btn accordion_block_btn_hide"><?=Loc::getMessage("ORDER_WORK_EDIT_SHOW")?></span>
					<?
					foreach($mes_arr as $mes)
						echo $mes."<br>";
					?>
				</div>
				<? }else{ ?>
					<?= $order -> getMessage() ?>
				<? } ?>
			</td>
		</tr>
		<? if($order -> getRegion()){ ?>
		<tr>
			<td class="adm-detail-valign-top adm-detail-content-cell-l"><?=Loc::getMessage("ORDER_WORK_EDIT_REGION")?></td>
			<td class="adm-detail-content-cell-r"><?= $order -> getRegion() -> getValue() ?></td>
		</tr>
		<? } ?>
		<?php if ($order -> getAddress()){
            $arr_address = explode(';',$order -> getAddress());
			$arr_address = array_unique($arr_address);
		?>
		<tr>
			<td class="adm-detail-valign-top adm-detail-content-cell-l"><?=Loc::getMessage("ORDER_WORK_EDIT_ADDRESS")?></td>
			<td class="adm-detail-content-cell-r">
			<? if(count($arr_address) > 1){ ?>
				<div class="accordion_block">
					<span class="accordion_block_btn accordion_block_btn_show"><?=Loc::getMessage("ORDER_WORK_EDIT_HIDE")?></span>
					<span class="accordion_block_btn accordion_block_btn_hide"><?=Loc::getMessage("ORDER_WORK_EDIT_SHOW")?></span>
				<?
				foreach($arr_address as $a_val){
					echo $a_val."<br>";
				} 
				?>
				</div>
			<? 
			}else{
				echo $arr_address[0];
			}
			?>
			</td>
		</tr>
		<? } ?>
		<tr>
			<td class="adm-detail-valign-top adm-detail-content-cell-l"><?=Loc::getMessage("ORDER_WORK_EDIT_INDUSTRY")?></td>
			<td class="adm-detail-content-cell-r"><?=$order -> getIndustry() -> getValue()?></td>
		</tr>
		<tr>
			<td style="width: 30%" class="adm-detail-valign-top adm-detail-content-cell-l"><?=Loc::getMessage("ORDER_WORK_EDIT_STATE")?></td>
			<td style="width: 70%" class="adm-detail-content-cell-r"><?=$order -> getState() -> getValue()?></td>
		</tr>
		<tr>
			<td style="width: 30%" class="adm-detail-valign-top adm-detail-content-cell-l"><?=Loc::getMessage("ORDER_WORK_EDIT_LEVEL")?></td>
			<td style="width: 70%" class="adm-detail-content-cell-r"><?=$order -> getLevel() -> getValue()?></td>
		</tr>
		<tr>
			<td class="adm-detail-valign-top adm-detail-content-cell-l"><?=Loc::getMessage("ORDER_WORK_EDIT_ORG")?></td>
			<td class="adm-detail-content-cell-r"><?=implode($orgs, '<br>')?></td>
		</tr>
		<tr>
			<td class="adm-detail-valign-top adm-detail-content-cell-l"><?=Loc::getMessage("ORDER_WORK_EDIT_ORG_S")?></td>
			<td class="adm-detail-content-cell-r"><?= $other_spa ? implode($other_spa, '<br>') : Loc::getMessage("ORDER_WORK_EDIT_ORG_S_NOT")?></td>
		</tr>
		<?
		if ($order -> getOfficer()){
			$officer_user_data = CUser::GetByID($order -> getOfficer())->Fetch();
		?>
			<tr>
				<td class="adm-detail-valign-top adm-detail-content-cell-l"><?=Loc::getMessage("ORDER_WORK_EDIT_OFFICIER")?></td>
				<td class="adm-detail-content-cell-r">
					<?= implode(array($officer_user_data['LAST_NAME'],$officer_user_data['NAME'],$officer_user_data['SECOND_NAME']),' ') ?>
				</td>
			</tr>
		<?
		}
		?>

		<?
		$tabControl->BeginNextTab();
		?>
		
		<tr class="heading">
			<td colspan="2"><?=Loc::getMessage("ORDER_WORK_EDIT_DATE")?></td>
		</tr>
		<tr>
			<td class="adm-detail-valign-top adm-detail-content-cell-l"><?=Loc::getMessage("ORDER_WORK_EDIT_LIMIT_DATE")?></td>
			<td class="adm-detail-content-cell-r"><?=$order -> getLimitDate()?></td>
		</tr>
		<tr>
			<td class="adm-detail-valign-top adm-detail-content-cell-l"><?=Loc::getMessage("ORDER_WORK_EDIT_LIMIT_PERIOD_DATE")?></td>
			<td class="adm-detail-content-cell-r"><?=$order -> getLimitPeriodDate()?></td>
		</tr>
		<tr class="heading">
			<td colspan="2"><?=Loc::getMessage("ORDER_WORK_EDIT_REPORT")?></td>
		</tr>
		<?php
		$cntReports = Order\ReportsTable::getList(
			array(
				'count_total' => true, 
				'filter' => array('=order_id' => $order -> getId())
			)
		) -> getCount();
		if (intval($cntReports) <= 0)
		{
		?>
			<tr>
				<td colspan="2"><center><?=Loc::getMessage("ORDER_WORK_EDIT_REPORT_NONE")?></center></td>
			</tr>
		<?
		}
		else
		{
		?>
			<tr>
				<td colspan="2">
					<table class="uiir-statistic-table">
						<tr>
							<th width="35%"><?=Loc::getMessage("ORDER_WORK_EDIT_REPORT_FORM")?></th>
							<th width="20%"><?=Loc::getMessage("ORDER_WORK_EDIT_REPORT_USER")?></th>
							<th width="20%"><?=Loc::getMessage("ORDER_WORK_EDIT_REPORT_RESOLVE_USER")?></th>
							<th width="25%"><?=Loc::getMessage("ORDER_WORK_EDIT_REPORT_RESOLUTION")?></th>
						</tr>
						<?
						foreach ($order -> getReports() as $report)
						{
							$fio = array();
							$fio[] = UserTable::getById($report -> getUserId()) -> fetchObject() -> getLastName();
							$fio[] = UserTable::getById($report -> getUserId()) -> fetchObject() -> getName();
							$fio[] = UserTable::getById($report -> getUserId()) -> fetchObject() -> getSecondName();
							if ($report -> getTimestampResolve())
							{
								$fio_resolve = array();
								$fio_resolve[] = UserTable::getById($report -> getUserResolveId()) -> fetchObject() -> getLastName();
								$fio_resolve[] = UserTable::getById($report -> getUserResolveId()) -> fetchObject() -> getName();
								$fio_resolve[] = UserTable::getById($report -> getUserResolveId()) -> fetchObject() -> getSecondName();
							}
							?>
							<tr>
								<td><?=$report -> getMessage()?></td>
								<td><?=$report -> getTimestamp()?><br><?=implode($fio, " ")?></td>
								<?
								if ($report -> getTimestampResolve())
								{
								?>
									<td><?=$report -> getTimestampResolve()?><br><?=implode($fio_resolve, " ")?></td>
									<td><?=$report -> getResolution()?></td>
								<?
								}
								else
								{
								?>
									<td></td>
									<td></td>
								<?
								}
								?>
							</tr>
						<?
						}
						?>
					</table>
				</td>
			</tr>
		<?
		}


		$tabControl->BeginNextTab();
		foreach ($order -> getHistory() as $history)
		{
			$fio = array();
			$fio[] = UserTable::getById($history -> getUserId()) -> fetchObject() -> getLastName();
			$fio[] = UserTable::getById($history -> getUserId()) -> fetchObject() -> getName();
			$fio[] = UserTable::getById($history -> getUserId()) -> fetchObject() -> getSecondName();
			?>
			<tr class="heading">
				<td colspan="2"><?=Order\OperationsTable::getById($history -> getOperationId()) -> fetchObject() -> getValue()?></td>
			</tr>
			<tr>
				<td style="width: 30%" class="adm-detail-valign-top adm-detail-content-cell-l"><?=Loc::getMessage("ORDER_WORK_EDIT_HISTORY_TIMESTAMP")?></td>
				<td style="width: 70%" class="adm-detail-content-cell-r"><?=$history -> getTimestamp()?></td>
			</tr>
			<tr>
				<td style="width: 30%" class="adm-detail-valign-top adm-detail-content-cell-l"><?=Loc::getMessage("ORDER_WORK_EDIT_HISTORY_USER")?></td>
				<td style="width: 70%" class="adm-detail-content-cell-r"><?=implode($fio, " ")?></td>
			</tr>
			<?if ($history -> getOld()){?>
			<tr>
				<td style="width: 30%" class="adm-detail-valign-top adm-detail-content-cell-l"><?=Loc::getMessage("ORDER_WORK_EDIT_HISTORY_OLD")?></td>
				<td style="width: 70%" class="adm-detail-content-cell-r"><?=$history -> getOld()?></td>
			</tr>
			<?}?>
			<?if ($history -> getNew()){?>
			<tr>
				<td style="width: 30%" class="adm-detail-valign-top adm-detail-content-cell-l"><?=Loc::getMessage("ORDER_WORK_EDIT_HISTORY_NEW")?></td>
				<td style="width: 70%" class="adm-detail-content-cell-r"><?=$history -> getNew()?></td>
			</tr>
		<?}?>
		<?
		}


		$tabControl->Buttons();
		switch ($order -> getState() -> getAlias())
		{
			case 'created':
				if ((in_array($order -> getOrganizationPrimary() -> getOrgId(), Order\UsersOrgTable::getUserOrg())) || (Order\UsersOrgTable::getUserRole() >= 'W' && Order\UsersOrgTable::getUserRole() < 'Y'))
				{?>
					<input class="adm-btn-save" type="button" id="addOfficerButton" value="<?=Loc::getMessage("ORDER_WORK_EDIT_ADD_OFFICER")?>" title="<?=Loc::getMessage("ORDER_WORK_EDIT_ADD_OFFICER")?>">&nbsp;
				<?}?>
				<input type="button" value="<?=Loc::getMessage('ORDER_WORK_EDIT_BACK')?>" name="cancel" onclick="window.location.href='uiir_order_order_in_new_list.php'; return false;" title="<?=Loc::getMessage('ORDER_WORK_EDIT_BACK_DESC')?>" />
				<?
				break;
			case 'checked':
				if ((in_array($order -> getOrganizationPrimary() -> getOrgId(), Order\UsersOrgTable::getUserOrg())) || (Order\UsersOrgTable::getUserRole() >= 'W' && Order\UsersOrgTable::getUserRole() < 'Y'))
				{?>
					<input class="adm-btn-save" type="button" id="iSeeButton" value="<?=Loc::getMessage("ORDER_WORK_EDIT_I_SEE")?>" title="<?=Loc::getMessage("ORDER_WORK_EDIT_I_SEE")?>">&nbsp;
				<?}?>
				<input type="button" value="<?=Loc::getMessage('ORDER_WORK_EDIT_BACK')?>" name="cancel" onclick="document.location.href='uiir_order_order_in_done_list.php'; return false;" title="<?=Loc::getMessage('ORDER_WORK_EDIT_BACK_DESC')?>" />
				<?
				break;
			case 'work':
				if ((in_array($order -> getOrganizationPrimary() -> getOrgId(), Order\UsersOrgTable::getUserOrg())) || (Order\UsersOrgTable::getUserRole() >= 'W' && Order\UsersOrgTable::getUserRole() < 'Y'))
				{
				?>
					<input class="adm-btn-save" type="button" id="sendReportButton" value="<?=Loc::getMessage("ORDER_WORK_EDIT_SEND_REPORT")?>" title="<?=Loc::getMessage("ORDER_WORK_EDIT_SEND_REPORT")?>">&nbsp;
				<?
				}
				$today = new Date();
				if (($order -> getLimitPeriodDate() -> getTimestamp()) > ($today -> add("7 day") -> getTimestamp()))
				{
				?>
					<input type="button" value="<?=Loc::getMessage('ORDER_WORK_EDIT_BACK')?>" name="cancel" onclick="document.location.href='uiir_order_order_work_list.php'; return false;" title="<?=Loc::getMessage('ORDER_WORK_EDIT_BACK_DESC')?>" />
				<?
				}
				elseif (($order -> getLimitPeriodDate() -> getTimestamp()) < ($today -> getTimestamp()))
				{
				?>
					<input type="button" value="<?=Loc::getMessage('ORDER_WORK_EDIT_BACK')?>" name="cancel" onclick="document.location.href='uiir_order_order_work_expired_list.php'; return false;" title="<?=Loc::getMessage('ORDER_WORK_EDIT_BACK_DESC')?>" />
				<?
				}
				else
				{
				?>
					<input type="button" value="<?=Loc::getMessage('ORDER_WORK_EDIT_BACK')?>" name="cancel" onclick="document.location.href='uiir_order_order_work_waiting_list.php'; return false;" title="<?=Loc::getMessage('ORDER_WORK_EDIT_BACK_DESC')?>" />
				<?
				}
				break;
			case 'mod':
				if (Order\UsersOrgTable::getUserRole() >= 'W' && Order\UsersOrgTable::getUserRole() < 'Y')
				{?>
					<input class="adm-btn-save" type="button" id="modButton" value="<?=Loc::getMessage("ORDER_WORK_EDIT_MOD")?>" title="<?=Loc::getMessage("ORDER_WORK_EDIT_MOD")?>">&nbsp;
					<!-- <input class="adm-btn-save" type="button" id="modRejButton" value="<?=Loc::getMessage("ORDER_WORK_EDIT_MOD_REJ")?>" title="<?=Loc::getMessage("ORDER_WORK_EDIT_MOD_REJ")?>">&nbsp; -->
					<? if($order->getTypeNakaz() == 0){ ?>
					<input class="adm-btn-save" type="button" id="modDoneButton" value="<?=Loc::getMessage("ORDER_WORK_EDIT_MOD_DONE")?>" title="<?=Loc::getMessage("ORDER_WORK_EDIT_MOD_DONE")?>">&nbsp;
					<? } ?>
				<?}?>
				<input type="button" value="<?=Loc::getMessage('ORDER_WORK_EDIT_BACK')?>" name="cancel" onclick="document.location.href='uiir_order_order_waiting_list.php'; return false;" title="<?=Loc::getMessage('ORDER_WORK_EDIT_BACK_DESC')?>" />
				<?
				break;
			case 'done':
				?>
				<input type="button" value="<?=Loc::getMessage('ORDER_WORK_EDIT_BACK')?>" name="cancel" onclick="document.location.href='uiir_order_order_done_list.php'; return false;" title="<?=Loc::getMessage('ORDER_WORK_EDIT_BACK_DESC')?>" />
				<input class="adm-btn-save" type="button" id="modButton" value="<?=Loc::getMessage("ORDER_WORK_EDIT_MOD")?>" title="<?=Loc::getMessage("ORDER_WORK_EDIT_MOD")?>">
				<?
				break;
			case 'rejected':
				?>
				<input type="button" value="<?=Loc::getMessage('ORDER_WORK_EDIT_BACK')?>" name="cancel" onclick="document.location.href='uiir_order_order_rejected_list.php'; return false;" title="<?=Loc::getMessage('ORDER_WORK_EDIT_BACK_DESC')?>" />
				<?
				break;
		}
		/* Изменен 19.02.2021 9:24 Лобанов */
		$user_role = Order\UsersOrgTable::getUserRole();
		if (in_array($user_role,array("E","W","X"))){
		$check_order = Order\ChangeOrdersTable::getCount(array('=order_id' => $id, '=status_id' => 'NULL'));
		if($check_order == 0){
			if (in_array($order -> getState() -> getAlias(), array('work','created'))){
				echo '<input type="button" value="'.Loc::getMessage('ORDER_WORK_EDIT_CHANGE_SPA').'" class="btn open_popup_window" data="change_spa_form" />';
			}
		}else{
			echo '<input type="button" disabled="true" value="' . Loc::getMessage('ORDER_WORK_EDIT_CHANGE_SPA_ERROR_IN_MOD') . '"';
		}
		}
		$tabControl->End();
		$tabControl->ShowWarnings("post_form", $messageError);
		?>

	</form>

	<div id="overlay"></div>

	<?if (($order -> getState() -> getAlias() == 'created') && ((in_array($order -> getOrganizationPrimary() -> getOrgId(), Order\UsersOrgTable::getUserOrg())) || (Order\UsersOrgTable::getUserRole() >= 'W'))){?>
		<div id="addOfficer" class="edit-popup">
			<form method="post" action="" enctype="multipart/form-data" name="post_form_addOfficer">
			<?echo bitrix_sessid_post();?>
			<p class="block-heading"><?=Loc::getMessage("ORDER_WORK_EDIT_FORM_TITLE")?></p>
			<div class="ui-alert ui-alert-warning">
				<span class="ui-alert-message"><?=Loc::getMessage("ORDER_WORK_EDIT_ADD_OFFICER_DESCRIPTION")?></span>
			</div>
			<p><strong><?=Loc::getMessage("ORDER_WORK_EDIT_OFFICIER")?></strong></p>

		<?
		$usersList = Order\UsersOrgTable::getList(array(
			'select' => array('ID','USER_ID', 'ORG_ID'),
			'filter' => array('=ORG_ID' => $order->getOrganizationPrimary()->getOrgId())
		)) -> fetchCollection();
		?>
			<select name="addOfficerString">
				<?
				foreach ($usersList as $usr){
					$usr_name = CUser::GetByID($usr->getUserId())->Fetch();
				?>
				<option value="<?= $usr_name["ID"] ?>"><?= $usr_name["LAST_NAME"].' '.$usr_name["NAME"] ?></option>
				<? } ?>
			</select>
			
			<p id="send-block-buttons">
				<input type="submit" class="adm-btn-save" name="addOfficerSave" value="<?=Loc::getMessage("ORDER_WORK_EDIT_SAVE")?>" title="<?=Loc::getMessage("ORDER_WORK_EDIT_SAVE")?>" />
				<input type="button" value="<?=Loc::getMessage("ORDER_WORK_EDIT_CANCEL")?>" id="addOfficerСancel" title="<?=Loc::getMessage("ORDER_WORK_EDIT_CANCEL")?>" />
			</p>
			<?if($id>0):?>
			<input type="hidden" name="id" value="<?=$id?>">
			<?endif;?> 
			</form>
		</div>
	<?}?>

	<?if ($order -> getState() -> getAlias() == 'checked') {?>
		<div id="iSee" class="edit-popup">
			<form method="post" action="" enctype="multipart/form-data" name="post_form_iSee">
			<?echo bitrix_sessid_post();?>
			<p class="block-heading"><?=Loc::getMessage("ORDER_WORK_EDIT_FORM_TITLE")?></p>
			<div class="ui-alert ui-alert-warning">
				<span class="ui-alert-message"><?=Loc::getMessage("ORDER_WORK_EDIT_I_SEE_DESCRIPTION")?></span>
			</div>
			<p id="send-block-buttons">
				<input type="submit" class="adm-btn-save" name="iSeeSave" value="<?=Loc::getMessage("ORDER_WORK_EDIT_SAVE")?>" title="<?=Loc::getMessage("ORDER_WORK_EDIT_SAVE")?>" />
				<input type="button" value="<?=Loc::getMessage("ORDER_WORK_EDIT_CANCEL")?>" id="iSeeСancel" title="<?=Loc::getMessage("ORDER_WORK_EDIT_CANCEL")?>" />
			</p>
			<?if($id>0):?>
			<input type="hidden" name="id" value="<?=$id?>">
			<?endif;?>
			</form>
		</div>
	<?}?>

	<?if ($order -> getState() -> getAlias() == 'work') {?>
		<div id="sendReport" class="edit-popup">
			<form method="post" action="" enctype="multipart/form-data" name="post_form_sendReport">
			<?echo bitrix_sessid_post();?>
			<p class="block-heading"><?=Loc::getMessage("ORDER_WORK_EDIT_FORM_TITLE")?></p>
			<div class="ui-alert ui-alert-warning">
				<span class="ui-alert-message"><?=Loc::getMessage("ORDER_WORK_EDIT_SEND_REPORT_DESCRIPTION")?></span>
			</div>
			<p>
				<input type="hidden" name="sendReportLevel" value="<?=($order->getLevel()->getId() ? $order->getLevel()->getId() : '3')?>">
				<? /* ?>
				<?=Loc::getMessage("ORDER_WORK_EDIT_LEVEL")?>
				<select name="sendReportLevel" size="3">
					<!-- <option value=""<?=(!$order -> getLevel() -> getId() ? ' selected' : '')?>><?=Loc::getMessage("ORDER_WORK_EDIT_FILTER_ANY")?></option> -->
					<?
					$levelsList = Order\LevelsTable::getList() -> fetchCollection();
					foreach ($levelsList as $level)
					{
						?>
						<option value="<?=$level -> getId()?>"<?=($order -> getLevel() -> getId() == $level -> getId() ? ' selected' : '')?>><?=$level -> getValue()?></option>
						<?
					}
					?>
				</select>
				<? */ ?>
			</p>
			<!--
			<p>
				<?=Loc::getMessage("ORDER_WORK_EDIT_BUDGET_PLAN_FORM")?>
				<input type="text" name="sendReportBudgetPlan" value="<?=($bVarsFromForm)?$sendReportBudgetPlan:$order -> getBudgetPlan()?>">
			</p>
			<p>
				<?=Loc::getMessage("ORDER_WORK_EDIT_BUDGET_FACT_FORM")?>
				<input type="text" name="sendReportBudgetFact" value="<?=($bVarsFromForm)?$sendReportBudgetFact:$order -> getBudgetFact()?>">
			</p>
			-->
			
			<p>
				<?=Loc::getMessage("ORDER_WORK_EDIT_REPORT_FORM")?>
				<textarea name="sendReportText" rows="6"></textarea>
			</p>
			<p id="send-block-buttons">
				<input type="submit" class="adm-btn-save" name="sendReportSave" value="<?=Loc::getMessage("ORDER_WORK_EDIT_SAVE")?>" title="<?=Loc::getMessage("ORDER_WORK_EDIT_SAVE")?>" />
				<input type="button" value="<?=Loc::getMessage("ORDER_WORK_EDIT_CANCEL")?>" id="sendReportСancel" title="<?=Loc::getMessage("ORDER_WORK_EDIT_CANCEL")?>" />
			</p>
			<?if($id>0):?>
			<input type="hidden" name="id" value="<?=$id?>">
			<?endif;?>
			</form>
		</div>
	<?}?>

	<?if ($order -> getState() -> getAlias() == 'mod' || $order -> getState() -> getAlias() == 'done') {?>
	<div id="mod" class="edit-popup">
		<form method="post" action="" enctype="multipart/form-data" name="post_form_mod">
		<?echo bitrix_sessid_post();?>
		<p class="block-heading"><?=Loc::getMessage("ORDER_WORK_EDIT_FORM_TITLE")?></p>
		<div class="ui-alert ui-alert-warning">
			<span class="ui-alert-message"><?=Loc::getMessage("ORDER_WORK_EDIT_MOD_DESCRIPTION")?></span>
		</div>
		<textarea name="sendResolutionText"><?=($bVarsFromForm)?$sendResolutionText:''?></textarea>
		<br><br>
		<div class="ui-alert ui-alert-warning">
			<span class="ui-alert-message"><?=Loc::getMessage("ORDER_WORK_EDIT_MOD_DESCRIPTION_EDIT")?></span>
		</div>
		<table style='width:100%;border:none'><tr>
			<td width='50%'>
				<p><strong><?=Loc::getMessage("ORDER_WORK_EDIT_LIMIT_DATE")?></strong></p>
				<?echo CalendarDate("sendResolutionLimitDate", ($bVarsFromForm)?$sendResolutionLimitDate:$order->getLimitDate(), "post_form_mod", "10")?>
			</td>
			<td width='50%'>
				<p><strong><?=Loc::getMessage("ORDER_WORK_EDIT_LIMIT_PERIOD_DATE")?></strong></p>
				<?echo CalendarDate("sendResolutionLimitPeriodDate", ($bVarsFromForm)?$sendResolutionLimitPeriodDate:$order->getLimitPeriodDate(), "post_form_mod", "10")?>
			</td>
		</tr></table>
		<p>
			<?=Loc::getMessage("ORDER_WORK_EDIT_ORG_P")?>
			<select name="sendResolutionOrgP" size="1" class="orgp_list_select">
				<?
				$organizationsList = Order\OrganizationsTable::getList(array('filter' => array('>role_id' => '1'))) -> fetchCollection();
				foreach ($organizationsList as $org)
				{
					?>
					<option value="<?=$org -> getId()?>"<?=($org -> getId() == $orgsPId[0] ? ' selected' : '')?>><?=$org -> getValue()?></option>
					<?
				}
				?>
			</select>
		</p>
		<p>
			<?=Loc::getMessage("ORDER_WORK_EDIT_OFFICIER")?>
			<select name="sendResolutionOfficer" size="1" class="officer_list_select">
				<?
				$usersorgsList = Order\UsersOrgTable::getList() -> fetchCollection();
				foreach ($usersorgsList as $usersorg)
				{
					?>
					<option data-org="<?=$usersorg -> getOrgId()?>" value="<?=$usersorg -> getUserId()?>" <?= $order->getOfficer() == $usersorg -> getUserId() ? 'selected' : '' ?>>
                        <?= UserTable::getById($usersorg -> getUserId()) -> fetchObject() -> getLastName().' '.
                            UserTable::getById($usersorg -> getUserId()) -> fetchObject() -> getName(); ?></option>
					<?
				}
				?>
			</select>
		</p>
		<p>
			<?=Loc::getMessage("ORDER_WORK_EDIT_ORG_S")?>
			<select name="sendResolutionOrgS[]" size="6" multiple>
				<?
				if ($bVarsFromForm)
					$showOrgs = $sendResolutionOrgS;
				else
					$showOrgs = $orgsId;
				$organizationsList = Order\OrganizationsTable::getList(array('filter' => array('>role_id' => '1'))) -> fetchCollection();
				foreach ($organizationsList as $org)
				{
					if (in_array($org -> getId(), $orgsId))
					{ 
					?>
					<option value="<?=$org -> getId()?>" selected><?=$org -> getValue()?></option>
					<?
					}
				}
				foreach ($organizationsList as $org)
				{
					if ((!in_array($org -> getId(), $showOrgs)) && (!in_array($org -> getId(), $showOrgs)))
					{
					?>
					<option value="<?=$org -> getId()?>"><?=$org -> getValue()?></option>
					<?
					}
				}
				?>
			</select>
		</p>
		<p id="send-block-buttons">
			<input type="submit" class="adm-btn-save" name="modSave" value="<?=Loc::getMessage("ORDER_WORK_EDIT_SAVE")?>" title="<?=Loc::getMessage("ORDER_WORK_EDIT_SAVE")?>" />
			<input type="button" value="<?=Loc::getMessage("ORDER_WORK_EDIT_CANCEL")?>" id="modСancel" title="<?=Loc::getMessage("ORDER_WORK_EDIT_CANCEL")?>" />
		</p>
		<?if($id>0):?>
		<input type="hidden" name="id" value="<?=$id?>">
		<?endif;?>
		</form>
	</div>
	<div id="modRej" class="edit-popup">
		<form method="post" action="" enctype="multipart/form-data" name="post_form_mod_rej">
		<?echo bitrix_sessid_post();?>
		<p class="block-heading"><?=Loc::getMessage("ORDER_WORK_EDIT_FORM_TITLE")?></p>
		<div class="ui-alert ui-alert-warning">
			<span class="ui-alert-message"><?=Loc::getMessage("ORDER_WORK_EDIT_MOD_REJ_DESCRIPTION")?></span>
		</div>
		<p id="send-block-buttons">
			<input type="submit" class="adm-btn-save" name="modRejSave" value="<?=Loc::getMessage("ORDER_WORK_EDIT_SAVE")?>" title="<?=Loc::getMessage("ORDER_WORK_EDIT_SAVE")?>" />
			<input type="button" value="<?=Loc::getMessage("ORDER_WORK_EDIT_CANCEL")?>" id="modRejСancel" title="<?=Loc::getMessage("ORDER_WORK_EDIT_CANCEL")?>" />
		</p>
		<?if($id>0):?>
		<input type="hidden" name="id" value="<?=$id?>">
		<?endif;?>
		</form>
	</div>
	<div id="modDone" class="edit-popup">
		<form method="post" action="" enctype="multipart/form-data" name="post_form_mod_done">
		<?echo bitrix_sessid_post();?>
		<p class="block-heading"><?=Loc::getMessage("ORDER_WORK_EDIT_FORM_TITLE")?></p>
		<div class="ui-alert ui-alert-warning">
			<span class="ui-alert-message"><?=Loc::getMessage("ORDER_WORK_EDIT_MOD_DONE_DESCRIPTION")?></span>
		</div>
		<p id="send-block-buttons">
			<input type="submit" class="adm-btn-save" name="modDoneSave" value="<?=Loc::getMessage("ORDER_WORK_EDIT_SAVE")?>" title="<?=Loc::getMessage("ORDER_WORK_EDIT_SAVE")?>" />
			<input type="button" value="<?=Loc::getMessage("ORDER_WORK_EDIT_CANCEL")?>" id="modDoneСancel" title="<?=Loc::getMessage("ORDER_WORK_EDIT_CANCEL")?>" />
		</p>
		<?if($id>0):?>
		<input type="hidden" name="id" value="<?=$id?>">
		<?endif;?>
		</form>
	</div>
	<?}
}
?>
<? /* Изменен 19.02.2021 9:24 Лобанов */
if (in_array($order -> getState() -> getAlias(), array('work','created'))){
?>
    <div id="change_spa_form" class="edit-popup">
		<form method="post" action="" enctype="multipart/form-data" name="post_change_spa_form">
			<?echo bitrix_sessid_post();?>
			<p class="block-heading"><?=Loc::getMessage("ORDER_WORK_EDIT_TITLE_CHANGE_SPA")?></p>
			<div class="ui-alert ui-alert-warning">
				<span class="ui-alert-message"><?=Loc::getMessage("ORDER_WORK_EDIT_DESCRIPTION_CHANGE_SPA")?></span>
			</div>
			<div>
			  <p>
			  <?=Loc::getMessage("ORDER_WORK_EDIT_CHANGE_SPA_TITLE_SELECT")?>
			  <select name="change_spa_select" required class="orgp_list_select">
			    <?
				$organizationsList = Order\OrganizationsTable::getList(array('filter' => array('>role_id' => '1'))) -> fetchCollection();
				foreach ($organizationsList as $org){ ?>
					<option value="<?=$org -> getId()?>" <?= $org->getId() == $orgsPId[0] ? 'selected' : '' ?>><?=$org -> getValue()?></option>
				<? } ?>
			  </select>
			  </p>
			  <p>
				<?=Loc::getMessage("ORDER_WORK_EDIT_OFFICIER")?>
				<select name="change_officer_select" size="1" class="officer_list_select">
					<?
					$usersorgsList = Order\UsersOrgTable::getList() -> fetchCollection();
					foreach ($usersorgsList as $usersorg){
					?>
						<option data-org="<?=$usersorg -> getOrgId()?>" value="<?=$usersorg -> getUserId()?>" <?= $order->getOfficer() == $usersorg -> getUserId() ? 'selected' : '' ?>><?= UserTable::getById($usersorg -> getUserId()) -> fetchObject() -> getLastName().' '.UserTable::getById($usersorg -> getUserId()) -> fetchObject() -> getName(); ?></option>
					<?
					}
					?>
				</select>
		      </p>
			  <p>
			  <?=Loc::getMessage("ORDER_WORK_EDIT_CHANGE_SPA_TITLE_TEXTAREA")?>
			  <textarea name="change_spa_text" required></textarea>
			  </p>
			</div>
			<p id="send-block-buttons">
				<input type="submit" class="adm-btn-save" name="changeSpaSave" value="<?=Loc::getMessage("ORDER_WORK_EDIT_SAVE")?>" title="<?=Loc::getMessage("ORDER_WORK_EDIT_SAVE")?>" />
				<input type="button" value="<?=Loc::getMessage("ORDER_WORK_EDIT_CANCEL")?>" id="changeSpaSancel" class="popupWindowСancel" title="<?=Loc::getMessage("ORDER_WORK_EDIT_CANCEL")?>" />
			</p>
			<?if($id>0):?>
			<input type="hidden" name="id" value="<?=$id?>">
			<?endif;?>
		</form>
	</div>
<? } ?>
<? require($_SERVER["DOCUMENT_ROOT"] . "/bitrix/modules/main/include/epilog_admin.php"); ?>