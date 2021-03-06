<?php
require_once dirname(__FILE__).'/../../../../../global.inc.php';
?>
<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.0 Transitional//EN">
<!--

/* For licensing terms, see /license.txt */

/**
 * Copyright (C) 2012 
 * AsciiMath plugin for CKEditor. Plugin developed by Dokeos Team based in the work of Peter Jipsen
 * 
 */
-->
<html xmlns="http://www.w3.org/1999/xhtml">
	<head>
		<title>AsciiMath Editor</title>
		<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
		<meta name="robots" content="noindex, nofollow" />
                <script type="text/javascript" src="asciimath/ASCIIMathML.js"></script>
                <script type="text/javascript" src="asciimath.js"></script>
                <script type="text/javascript" src="../../../ckeditor.js"></script>
		<style type="text/css">
			body, td, input, textarea, select, label { font-family: Arial, Verdana, Geneva, helvetica, sans-serif; font-size: 11px; }
		</style>
  <style type="text/css">

form { padding: 0px; margin: 0px; }
form p { margin-top: 5px; margin-bottom: 5px; }
table { font: 11px Tahoma,Verdana,sans-serif; }
select, input, button { font: 11px Tahoma,Verdana,sans-serif; }
table .label { text-align: right; width: 8em; }
.fl { width: 9em; float: left; padding: 2px 5px; text-align: right; }
.fr { width: 7em; float: left; padding: 2px 5px; text-align: right; }
fieldset { padding: 0px 10px 5px 5px; }
.space { padding: 2px; }

#buttons {
      margin-top: 10px;
}

	#outputNode,#inputText,#mathml {
		padding:5px;
		background-color:white;
		width:98%;
		height:130px;
		font-size:1.3em;
		border: 1px solid darkgrey;
		overflow:auto;
	}
	#clickInput {
		width:100%;
		border-collapse:collapse;
		background-color: white;
		text-align:center;
	}
	#clickInput td {
		border: thin solid gray;
		cursor:pointer;
		font-size:1.1em;

	}
	h3 {
		font-size:1.6em;
	}
  </style>

	</head>
<body scroll="no" style="overflow: hidden;">
<!--
Table modified from CharacterMap for ASCIIMathML by Peter Jipsen
HTMLSource based on HTMLArea XTD 1.5 (http://mosforge.net/projects/htmlarea3xtd/) modified by Holger Hees
Original Author - Bernhard Pfeifer novocaine@gmx.net
-->
<table  id="clickInput">
<tr>
<td colspan="3" class="character" title="(x+1)/(x-1)" onclick="javascript: Set('(x+1)/(x-1)');">`(x+1)/(x-1)`</td>
<td colspan="2" class="character" title="x^(m+n)" onclick="javascript: Set('x^(m+n)');">`x^(m+n)`</td>
<td colspan="2" class="character" title="x_(mn)" onclick="javascript: Set('x_(mn)');">`x_(mn)`</td>
<td colspan="2" class="character" title="sqrt(x)" onclick="javascript: Set('sqrt(x)');">`sqrt(x)`</td>
<td colspan="3" class="character" title="root(n)(x)" onclick="javascript: Set('root(n)(x)');">`root(n)(x)`</td>
<td colspan="2" class="character" title="&quot;text&quot;" onclick="javascript: Set('&quot;text&quot;');">`&quot;text&quot;`</td>
<td colspan="2" class="character" style="cursor:default"></td>
</tr><tr>
<td colspan="2" class="character" title="dy/dx" onclick="javascript: Set('dy/dx');">`dy/dx`</td>
<td colspan="3" class="character" title="lim_(x-&gt;oo)" onclick="javascript: Set('lim_(x-&gt;oo)');">`lim_(x-&gt;oo)`</td>
<td colspan="3" class="character" title="sum_(n=1)^oo" onclick="javascript: Set('sum_(n=1)^oo');">`sum_(n=1)^oo`</td>
<td colspan="3" class="character" title="int_a^bf(x)dx" onclick="javascript: Set('int_a^bf(x)dx');">`int_a^bf(x)dx`</td>
<td colspan="3" class="character" title="[[a,b],[c,d]]" onclick="javascript: Set('[[a,b],[c,d]]');">`[[a,b],[c,d]]`</td>
<td colspan="2" class="character" title="((n),(k))" onclick="javascript: Set('((n),(k))');">`((n),(k))`</td>
</tr><tr>
<td class="character" title="*" onclick="javascript: Set('*');">`*`</td>
<td class="character" title="**" onclick="javascript: Set('**');">`**`</td>
<td class="character" title="//" onclick="javascript: Set('//');">`//`</td>
<td class="character" title="\\" onclick="javascript: Set('\\\\');">`\\`</td>
<td class="character" title="xx" onclick="javascript: Set('xx');">`xx`</td>
<td class="character" title="-:" onclick="javascript: Set('-:');">`-:`</td>
<td class="character" title="@" onclick="javascript: Set('@');">`@`</td>
<td class="character" title="o+" onclick="javascript: Set('o+');">`o+`</td>
<td class="character" title="ox" onclick="javascript: Set('ox');">`ox`</td>
<td class="character" title="o." onclick="javascript: Set('o.');">`o.`</td>
<td class="character" title="sum" onclick="javascript: Set('sum');">`sum`</td>
<td class="character" title="prod" onclick="javascript: Set('prod');">`prod`</td>
<td class="character" title="^^" onclick="javascript: Set('^^');">`^^`</td>
<td class="character" title="^^^" onclick="javascript: Set('^^^');"><span style="font-size:larger">`&and;`</span></td>
<td class="character" title="vv" onclick="javascript: Set('vv');">`vv`</td>
<td class="character" title="vvv" onclick="javascript: Set('vvv');"><span style="font-size:larger">`&or;`</span></td>
</tr><tr>
<td class="character" title="!=" onclick="javascript: Set('!=');">`!=`</td>
<td class="character" title="&lt;=" onclick="javascript: Set('&lt;=');">`&lt;=`</td>
<td class="character" title="&gt;=" onclick="javascript: Set('&gt;=');">`&gt;=`</td>
<td class="character" title="-&lt;" onclick="javascript: Set('-&lt;');">`-&lt;`</td>
<td class="character" title="&gt;-" onclick="javascript: Set('&gt;-');">`&gt;-`</td>
<td class="character" title="in" onclick="javascript: Set('in');">`in`</td>
<td class="character" title="!in" onclick="javascript: Set('!in');">`!in`</td>
<td class="character" title="sub" onclick="javascript: Set('sub');">`sub`</td>
<td class="character" title="sup" onclick="javascript: Set('sup');">`sup`</td>
<td class="character" title="sube" onclick="javascript: Set('sube');">`sube`</td>
<td class="character" title="supe" onclick="javascript: Set('supe');">`supe`</td>
<td class="character" title="O/" onclick="javascript: Set('O/');">`O/`</td>
<td class="character" title="nn" onclick="javascript: Set('nn');">`nn`</td>
<td class="character" title="nnn" onclick="javascript: Set('nnn');"><span style="font-size:larger">`&cap;`</span></td>
<td class="character" title="uu" onclick="javascript: Set('uu');">`uu`</td>
<td class="character" title="uuu" onclick="javascript: Set('uuu');"><span style="font-size:larger">`&cup;`</span></td>
</tr><tr>
<td class="character" title="and" onclick="javascript: Set('and');">`and`</td>
<td class="character" title="or" onclick="javascript: Set('or');">`or`</td>
<td class="character" title="not" onclick="javascript: Set('not');">`not`</td>
<td class="character" title="==&gt;" onclick="javascript: Set('==&gt;');">`==&gt;`</td>
<td class="character" title="if" onclick="javascript: Set('if');">`if`</td>
<td class="character" title="&lt;=&gt;" onclick="javascript: Set('&lt;=&gt;');">`&lt;=&gt;`</td>
<td class="character" title="AA" onclick="javascript: Set('AA');">`AA`</td>
<td class="character" title="EE" onclick="javascript: Set('EE');">`EE`</td>
<td class="character" title="_|_" onclick="javascript: Set('_|_');">`_|_`</td>
<td class="character" title="TT" onclick="javascript: Set('TT');">`TT`</td>
<td class="character" title="|--" onclick="javascript: Set('|--');">`|--`</td>
<td class="character" title="|==" onclick="javascript: Set('|==');">`|==`</td>
<td class="character" title="-=" onclick="javascript: Set('-=');">`-=`</td>
<td class="character" title="~=" onclick="javascript: Set('~=');">`~=`</td>
<td class="character" title="~~" onclick="javascript: Set('~~');">`~~`</td>
<td class="character" title="prop" onclick="javascript: Set('prop');">`prop`</td>
</tr><tr>
<td class="character" title="int" onclick="javascript: Set('int');">`int`</td>
<td class="character" title="oint" onclick="javascript: Set('oint');">`oint`</td>
<td class="character" title="del" onclick="javascript: Set('del');">`del`</td>
<td class="character" title="grad" onclick="javascript: Set('grad');">`grad`</td>
<td class="character" title="+-" onclick="javascript: Set('+-');">`+-`</td>
<td class="character" title="oo" onclick="javascript: Set('oo');">`oo`</td>
<td class="character" title="aleph" onclick="javascript: Set('aleph');">`aleph`</td>
<td class="character" title="quad" onclick="javascript: Set('quad');">`quad`</td>
<td class="character" title="diamond" onclick="javascript: Set('diamond');">`diamond`</td>
<td class="character" title="square" onclick="javascript: Set('square');">`square`</td>
<td class="character" title="|__" onclick="javascript: Set('|__');">`|__`</td>
<td class="character" title="__|" onclick="javascript: Set('__|');">`__|`</td>
<td class="character" title="|~" onclick="javascript: Set('|~');">`|~`</td>
<td class="character" title="~|" onclick="javascript: Set('~|');">`~|`</td>
<td class="character" title="&lt;x&gt;" onclick="javascript: Set('&lt;x&gt;');">`&lt;x&gt;`</td>
<td class="character" title="/_" onclick="javascript: Set('/_');">`/_`</td>
</tr><tr>
<td class="character" title="uarr" onclick="javascript: Set('uarr');">`uarr`</td>
<td class="character" title="darr" onclick="javascript: Set('darr');">`darr`</td>
<td class="character" title="larr" onclick="javascript: Set('larr');">`larr`</td>
<td class="character" title="-&gt;" onclick="javascript: Set('-&gt;');">`-&gt;`</td>
<td class="character" title="|-&gt;" onclick="javascript: Set('|-&gt;');">`|-&gt;`</td>
<td class="character" title="harr" onclick="javascript: Set('harr');">`harr`</td>
<td class="character" title="lArr" onclick="javascript: Set('lArr');">`lArr`</td>
<td class="character" title="rArr" onclick="javascript: Set('rArr');">`rArr`</td>
<td class="character" title="hArr" onclick="javascript: Set('hArr');">`hArr`</td>
<td class="character" title="hata" onclick="javascript: Set('hat');">`hata`</td>
<td class="character" title="ula" onclick="javascript: Set('ul');">`ula`</td>
<td class="character" title="dota" onclick="javascript: Set('dot');">`dota`</td>
<td class="character" title="ddota" onclick="javascript: Set('ddot');">`ddota`</td>
<td class="character" title="veca" onclick="javascript: Set('vec');">`veca`</td>
<td class="character" title="bara" onclick="javascript: Set('bar');">`bara`</td>
<td class="character" title=":." onclick="javascript: Set(':.');">`:.`</td>
</tr><tr>
<td class="character" title="NN" onclick="javascript: Set('NN');">`NN`</td>
<td class="character" title="ZZ" onclick="javascript: Set('ZZ');">`ZZ`</td>
<td class="character" title="QQ" onclick="javascript: Set('QQ');">`QQ`</td>
<td class="character" title="RR" onclick="javascript: Set('RR');">`RR`</td>
<td class="character" title="CC" onclick="javascript: Set('CC');">`CC`</td>
<td class="character" title="bba" onclick="javascript: Set('bb');">`bba`</td>
<td class="character" title="bbba" onclick="javascript: Set('bbb');">`bbba`</td>
<td class="character" title="cca" onclick="javascript: Set('cc');">`cca`</td>
<td class="character" title="fra" onclick="javascript: Set('fr');">`fra`</td>
<td class="character" title="sfa" onclick="javascript: Set('sf');">`sfa`</td>
<td class="character" title="tta" onclick="javascript: Set('tt');">`tta`</td>
<td colspan="4" class="character" title="stackrel(-&gt;)(+)" onclick="javascript: Set('stackrel(-&gt;)(+)');">`stackrel(-&gt;)(+)`</td>
<td class="character" title="upsilon" onclick="javascript: Set('upsilon');">`upsilon`</td>
</tr><tr>
<td class="character" title="alpha" onclick="javascript: Set('alpha');">`alpha`</td>
<td class="character" title="beta" onclick="javascript: Set('beta');">`beta`</td>
<td class="character" title="gamma" onclick="javascript: Set('gamma');">`gamma`</td>
<td class="character" title="Gamma" onclick="javascript: Set('Gamma');">`Gamma`</td>
<td class="character" title="delta" onclick="javascript: Set('delta');">`delta`</td>
<td class="character" title="Delta" onclick="javascript: Set('Delta');">`Delta`</td>
<td class="character" title="epsi" onclick="javascript: Set('epsi');">`epsi`</td>
<td class="character" title="zeta" onclick="javascript: Set('zeta');">`zeta`</td>
<td class="character" title="eta" onclick="javascript: Set('eta');">`eta`</td>
<td class="character" title="theta" onclick="javascript: Set('theta');">`theta`</td>
<td class="character" title="Theta" onclick="javascript: Set('Theta');">`Theta`</td>
<td class="character" title="iota" onclick="javascript: Set('iota');">`iota`</td>
<td class="character" title="kappa" onclick="javascript: Set('kappa');">`kappa`</td>
<td class="character" title="lambda" onclick="javascript: Set('lambda');">`lambda`</td>
<td class="character" title="Lambda" onclick="javascript: Set('Lambda');">`Lambda`</td>
<td class="character" title="mu" onclick="javascript: Set('mu');">`mu`</td>
</tr><tr>
<td class="character" title="nu" onclick="javascript: Set('nu');">`nu`</td>
<td class="character" title="pi" onclick="javascript: Set('pi');">`pi`</td>
<td class="character" title="Pi" onclick="javascript: Set('Pi');">`Pi`</td>
<td class="character" title="rho" onclick="javascript: Set('rho');">`rho`</td>
<td class="character" title="sigma" onclick="javascript: Set('sigma');">`sigma`</td>
<td class="character" title="Sigma" onclick="javascript: Set('Sigma');">`Sigma`</td>
<td class="character" title="tau" onclick="javascript: Set('tau');">`tau`</td>
<td class="character" title="xi" onclick="javascript: Set('xi');">`xi`</td>
<td class="character" title="Xi" onclick="javascript: Set('Xi');">`Xi`</td>
<td class="character" title="phi" onclick="javascript: Set('phi');">`phi`</td>
<td class="character" title="Phi" onclick="javascript: Set('Phi');">`Phi`</td>
<td class="character" title="chi" onclick="javascript: Set('chi');">`chi`</td>
<td class="character" title="psi" onclick="javascript: Set('psi');">`psi`</td>
<td class="character" title="Psi" onclick="javascript: Set('Psi');">`Psi`</td>
<td class="character" title="omega" onclick="javascript: Set('omega');">`omega`</td>
<td class="character" title="Omega" onclick="javascript: Set('Omega');">`Omega`</td>
</tr>
</table>
<form action="javascript: void(0);">
<table style="width: 100%; border: none;">
  <tr>
    <td style="width:50%;" nowrap="nowrap"><span ckLang="DlgAsciiMathInput"><?php echo get_lang('Submit'); ?></span>&nbsp;&nbsp;<input id="clear" type="button" ckLang="DlgAsciiMathClear" onclick="javascript: Clear();" style="width: 100px; font-size: 10px;" value="<?php echo get_lang('Clear'); ?>" />&nbsp;&nbsp;<input id="delete" type="button" ckLang="DlgAsciiMathDelete" onclick="javascript: Delete();" style="width: 100px; font-size: 10px;" value="<?php echo get_lang('Delete'); ?>" />&nbsp;&nbsp;</td>
    <td style="width:50%;"><input id="show_mathml" type="button" ckLang="DlgAsciiMathShowMathML" onclick="javascript: ShowMathML('<?php echo get_lang('ShowMathML'); ?>','<?php echo api_utf8_encode(get_lang('formulaPreview')); ?>');" style="float: right; font-size: 10px;" value="<?php echo get_lang('ShowMathML'); ?>" /><span ckLang="DlgAsciiMathPreview"><?php echo api_utf8_encode(get_lang('Preview')); ?></span></td>
  </tr>
  <tr>
    <td>
    <textarea id="inputText" onkeyup="javascript: Preview();"></textarea>
    </td>
    <td>
    <div id="outputNode"></div>
    <div id="outputNodeFinal" style="display:none;"></div>
    </td>
  </tr>
  <tr><td colspan="2">
  <span ckLang="DlgAsciiMathBasedOn">Based on ASCIIMathML by </span><a href="http://www.chapman.edu/~jipsen" onclick="javascript: window.open(this.href,'_blank','');return false;">Peter Jipsen</a>,
<a href="http://www.chapman.edu" onclick="javascript: window.open(this.href,'_blank','');return false;">Chapman University</a><br />
  <span ckLang="DlgAsciiMathForMoreInfo">For more information on AsciiMathML visit this page: </span><a href="http://www1.chapman.edu/~jipsen/mathml/asciimath.html" onclick="javascript: window.open(this.href,'_blank','');return false;">http://www1.chapman.edu/~jipsen/mathml/asciimath.html</a></td></tr>
</table>

<div id="buttons">
<script type="text/javascript">
if ( !CheckBrowserCompatibility() )
{
	CheckBrowserCompatibility( true ) ;
}

//New symbol added by Ricardo Garcia
define("==>","\u27F9");
</script> 
</div>
</form>

</body>
</html>
