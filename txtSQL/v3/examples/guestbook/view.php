<center>
<input type="button" onclick="document.location.href='index.php?action=post'" value="Sign Guestbook"></center><br/><br/>
<table width="100%" cellspacing="0" cellpadding="0" style="border:1px solid;border-color:#E0E0E0">

<?php

/* Grab all the entries from the databases, sorted w/ the newest on top */
$entries = $sql->query('SELECT * FROM ' . $table . ' ORDERBY date DESC');

/* Issue an error if there are no entries in the guestbook */
if ( empty($entries) )
{
	echo "<tr><td bgcolor=\"#F0F0F0\"><center><B>No Entries Found</B></center></td></tr>";
}

/* Display all the entries one by one with all of its respective information */
foreach ( $entries as $key => $entry )
{

	?>
	<tr valign="top">
		<td bgcolor="#F3F3F3">
			<table width="100%" cellspacing="1" cellpadding="5">
				<tr><td bgcolor="#F0F0F0"><b>AIM</td><td bgcolor="#F3F3F3"><?php echo $entry['aim']; ?></td></tr>
				<tr><td bgcolor="#F0F0F0"><b>MSN</td><td bgcolor="#F3F3F3"><?php echo $entry['msn']; ?></td></tr>
				<tr><td bgcolor="#F0F0F0"><b>Yahoo</td><td bgcolor="#F3F3F3"><?php echo $entry['yahoo']; ?></td></tr>
				<tr><td bgcolor="#F0F0F0"><b>ICQ</td><td bgcolor="#F3F3F3"><?php echo $entry['icq']; ?></td></tr>
				<tr><td bgcolor="#F0F0F0"><b>www</td><td bgcolor="#F3F3F3"><a href="<?php echo $entry['www']; ?>"><?php echo $entry['www']; ?></a></td></tr>
			</table>
		</td>
		<td width="75%" bgcolor="#F3F3F3" style="padding-top: 5px; padding-left: 5px;">
			Posted by <a href="mailto:<?php echo $entry['email']; ?>"><?php echo $entry['author']; ?></a> on <?php echo date('F j, Y, g:i a', $entry['date']); ?>
			<hr size="1" color="#404040"/>
			<?php echo nl2br(wordwrap(htmlentities($entry['entry']), 100)); ?>
		</td>
	</tr>
	<tr>
		<td height="5" bgcolor="#E0E0E0" colspan="2"></td>
	</tr>
	<?
}

echo "</table>";
?>