<?php

/**
 * @file controllers/grid/pubIds/PubIdExportSubmissionsListGridHandler.inc.php
 *
 * Copyright (c) 2014-2016 Simon Fraser University Library
 * Copyright (c) 2000-2016 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class PubIdExportSubmissionsListGridHandler
 * @ingroup controllers_grid_pubIds
 *
 * @brief Handle exportable submissions with pub ids list grid requests.
 */

import('lib.pkp.classes.controllers.grid.GridHandler');

import('lib.pkp.controllers.grid.submissions.exportableSubmissions.ExportableSubmissionsGridRow');
import('controllers.grid.pubIds.PubIdExportSubmissionsListGridCellProvider');

class PubIdExportSubmissionsListGridHandler extends GridHandler {
	/** @var boolean true if the current user has a managerial role */
	var $_isManager;

	/** @var ImportExportPlugin */
	var $_plugin;

	/**
	 * Constructor
	 */
	function PubIdExportSubmissionsListGridHandler() {
		parent::GridHandler();
		$this->addRoleAssignment(
			array(ROLE_ID_MANAGER),
			array('fetchGrid', 'fetchRow')
		);
	}

	//
	// Implement template methods from PKPHandler
	//
	/**
	 * @copydoc PKPHandler::authorize()
	 */
	function authorize($request, &$args, $roleAssignments) {
		import('lib.pkp.classes.security.authorization.ContextAccessPolicy');
		$this->addPolicy(new ContextAccessPolicy($request, $roleAssignments));
		return parent::authorize($request, $args, $roleAssignments);
	}

	/**
	 * @copydoc PKPHandler::initialize()
	 */
	function initialize($request) {
		parent::initialize($request);
		$context = $request->getContext();

		// Basic grid configuration.
		$this->setTitle('plugins.importexport.common.export.articles');

		// Load submission-specific translations.
		AppLocale::requireComponents(
			LOCALE_COMPONENT_APP_COMMON,
			LOCALE_COMPONENT_APP_SUBMISSION,
			LOCALE_COMPONENT_PKP_SUBMISSION
		);

		// Fetch the authorized roles and determine if the user is a manager.
		$authorizedRoles = $this->getAuthorizedContextObject(ASSOC_TYPE_USER_ROLES);
		$this->_isManager = in_array(ROLE_ID_MANAGER, $authorizedRoles);

		$pluginCategory = $request->getUserVar('category');
		$pluginPathName = $request->getUserVar('plugin');
		$this->_plugin = PluginRegistry::loadPlugin($pluginCategory, $pluginPathName);

		// Grid columns.
		$cellProvider = new PubIdExportSubmissionsListGridCellProvider($this->_plugin, $authorizedRoles);
		$this->addColumn(
			new GridColumn(
				'id',
				null,
				__('common.id'),
				'controllers/grid/gridCell.tpl',
				$cellProvider,
				array('alignment' => COLUMN_ALIGNMENT_LEFT,
						'width' => 10)
			)
		);
		$this->addColumn(
			new GridColumn(
				'title',
				'grid.submission.itemTitle',
				null,
				null,
				$cellProvider,
				array('html' => true,
						'alignment' => COLUMN_ALIGNMENT_LEFT)
			)
		);
		$this->addColumn(
			new GridColumn(
				'issue',
				'issue.issue',
				null,
				null,
				$cellProvider,
				array('alignment' => COLUMN_ALIGNMENT_LEFT,
					'width' => 20)
			)
		);
		$this->addColumn(
			new GridColumn(
				'pubId',
				null,
				$this->_plugin->getPubIdDisplayType(),
				null,
				$cellProvider,
				array('alignment' => COLUMN_ALIGNMENT_LEFT,
						'width' => 15)
			)
		);
		$this->addColumn(
			new GridColumn(
				'status',
				'common.status',
				null,
				null,
				$cellProvider,
				array('alignment' => COLUMN_ALIGNMENT_LEFT,
						'width' => 10)
			)
		);

	}


	//
	// Implemented methods from GridHandler.
	//
	/**
	 * @copydoc GridHandler::getRowInstance()
	 */
	function getRowInstance() {
		return new GridRow();
	}

	/**
	 * @copydoc GridHandler::initFeatures()
	 */
	function initFeatures($request, $args) {
		import('lib.pkp.classes.controllers.grid.feature.selectableItems.SelectableItemsFeature');
		import('lib.pkp.classes.controllers.grid.feature.PagingFeature');
		return array(new SelectableItemsFeature(), new PagingFeature());
	}

	/**
	 * @copydoc GridHandler::getRequestArgs()
	 */
	function getRequestArgs() {
		return array_merge(parent::getRequestArgs(), array('category' => $this->_plugin->getCategory(), 'plugin' => basename($this->_plugin->getPluginPath())));
	}

	/**
	 * @copydoc GridHandler::isDataElementSelected()
	 */
	function isDataElementSelected($gridDataElement) {
		return false; // Nothing is selected by default
	}

	/**
	 * @copydoc GridHandler::getSelectName()
	 */
	function getSelectName() {
		return 'selectedSubmissions';
	}

	/**
	 * @copydoc GridHandler::getFilterForm()
	 */
	protected function getFilterForm() {
		return 'controllers/grid/pubIds/pubIdExportSubmissionsGridFilter.tpl';
	}

	/**
	 * @copydoc GridHandler::renderFilter()
	 */
	function renderFilter($request, $filterData = array()) {
		$context = $request->getContext();
		$issueDao = DAORegistry::getDAO('IssueDAO');
		$issuesIterator = $issueDao->getPublishedIssues($context->getId());
		$issues = $issuesIterator->toArray();
		foreach ($issues as $issue) {
			$issueOptions[$issue->getId()] = $issue->getIssueIdentification();
		}
		$issueOptions[0] = __('plugins.importexport.common.filter.issue');
		ksort($issueOptions);
		$statusNames = $this->_plugin->getStatusNames();
		$filterColumns = $this->getFilterColumns();
		$filterData = array(
			'columns' => $filterColumns,
			'issues' => $issueOptions,
			'status' => $statusNames,
			'gridId' => $this->getId(),
		);
		return parent::renderFilter($request, $filterData);
	}

	/**
	 * @copydoc GridHandler::getFilterSelectionData()
	 */
	function getFilterSelectionData($request) {
		$search = (string) $request->getUserVar('search');
		$column = (string) $request->getUserVar('column');
		$issueId = (int) $request->getUserVar('issueId');
		$statusId = (string) $request->getUserVar('statusId');
		return array(
			'search' => $search,
			'column' => $column,
			'issueId' => $issueId,
			'statusId' => $statusId,
		);
	}

	/**
	 * @copydoc GridHandler::loadData()
	 */
	protected function loadData($request, $filter) {
		$publishedArticleDao = DAORegistry::getDAO('PublishedArticleDAO');
		$context = $request->getContext();
		list($search, $column, $issueId, $statusId) = $this->getFilterValues($filter);
		$title = $author = null;
		if ($column == 'title') {
			$title = $search;
		} elseif ($column == 'author') {
			$author = $search;
		}
		$pubIdStatusSettingName = null;
		if ($statusId) {
			$pubIdStatusSettingName = $this->_plugin->getDepositStatusSettingName();
		}
		return $publishedArticleDao->getByPubIdType(
			$this->_plugin->getPubIdType(),
			$context?$context->getId():null,
			$title,
			$author,
			$issueId,
			$pubIdStatusSettingName,
			$statusId,
			$this->getGridRangeInfo($request, $this->getId())
		);
	}


	//
	// Own protected methods
	//
	/**
	 * Get which columns can be used by users to filter data.
	 * @return array
	 */
	protected function getFilterColumns() {
		return array(
			'title' => __('submission.title'),
			'author' => __('submission.authors')
		);
	}

	/**
	 * Process filter values, assigning default ones if
	 * none was set.
	 * @return array
	 */
	protected function getFilterValues($filter) {
		if (isset($filter['search']) && $filter['search']) {
			$search = $filter['search'];
		} else {
			$search = null;
		}
		if (isset($filter['column']) && $filter['column']) {
			$column = $filter['column'];
		} else {
			$column = null;
		}
		if (isset($filter['issueId']) && $filter['issueId']) {
			$issueId = $filter['issueId'];
		} else {
			$issueId = null;
		}
		if (isset($filter['statusId']) && $filter['statusId'] != DOI_EXPORT_STATUS_ANY) {
			$statusId = $filter['statusId'];
		} else {
			$statusId = null;
		}
		return array($search, $column, $issueId, $statusId);
	}

}

?>