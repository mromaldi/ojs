{**
 * plugins/generic/counter/index.tpl
 *
 * Copyright (c) 2014-2016 Simon Fraser University Library
 * Copyright (c) 2003-2016 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * Counter plugin index
 *}
{strip}
{assign var="pageTitle" value="plugins.generic.counter"}
{include file="common/header.tpl"}
{/strip}

<p>{translate key="plugins.generic.counter.description"}</p>

<ul>
  <li>{translate key="plugins.generic.counter.1a.title"}{foreach from=$years item=year}&nbsp;&nbsp;<a href="{url op="report" year=$year}">{$year|escape}</a>{/foreach}</li>
  <li>XML version {foreach from=$years item=year}&nbsp;&nbsp;<a href="{url op="reportxml" year=$year}">{$year|escape}</a>{/foreach}</li>
  <li>SUSHI SOAP endpoint: sushixml</li>
  {call_hook name="Template::Plugin::Generic::Counter::Index"}
</ul>

{include file="common/footer.tpl"}
