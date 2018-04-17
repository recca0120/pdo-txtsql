<?php
/************************************************************************
* txtSQL                                                  ver. 3.0 BETA *
*************************************************************************
* This program is free software; you can redistribute it and/or         *
* modify it under the terms of the GNU General Public License           *
* as published by the Free Software Foundation; either version 2        *
* of the License, or (at your option) any later version.                *
*                                                                       *
* This program is distributed in the hope that it will be useful,       *
* but WITHOUT ANY WARRANTY; without even the implied warranty of        *
* MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the         *
* GNU General Public License for more details.                          *
*                                                                       *
* You should have received a copy of the GNU General Public License     *
* along with this program; if not, write to the Free Software           *
* Foundation, Inc., 59 Temple Place - Suite 330, Boston, MA  02111-1307 *
* USA.                                                                  *
*-----------------------------------------------------------------------*
*  NOTE- Tab size in this file: 8 spaces/tab                            *
*-----------------------------------------------------------------------*
*  �2003 Faraz Ali, ChibiGuy Production [http://txtsql.sourceforge.net] *
*  File: txtSQL.core.php                                                *
************************************************************************/

/**
 * The core file of the txtSQL package, this is the meat of the script
 * because most of the magic happens within here.
 *
 * @package txtSQL::txtSQLCore
 * @author Faraz Ali <FarazAli at Gmail dot com>
 * @version 3.0 BETA
 * @access private
 */
class txtSQLCore extends txtSQL
{
    /**
     * The constructor of the txtSQLCore class
     * @param string $path The path to which the databases are located
     * @return void
     * @access public
     */
    public function __construct($path = './data')
    {
        if (!is_dir($path)) {
            return $this->_error(E_USER_ERROR, 'Invalid data directory specified');
        }

        $this->_LIBPATH = $path;

        return true;
    }

    /**
     * To extract data from a database, given that the row fits the given credentials
     * @param mixed arg The arguments that are passed to the txtSQL as an array.
     * @return mixed selected An array containing the rows that matched the where clause
     * @access private
     */
    public function select($arg)
    {
        /* Do some error checking */
        if (!empty($arg['db'])) {
            if (!$this->selectDb($arg['db'])) {
                return false;
            }
        }

        if (empty($this->_SELECTEDDB)) {
            return $this->_error(E_USER_NOTICE, 'No database selected');
        } elseif (empty($arg['table'])) {
            return $this->_error(E_USER_NOTICE, 'No table specified');
        }

        $arg['select'] = empty($arg['select']) ? array('*') : $arg['select'];
        $filename      = "$this->_LIBPATH/$this->_SELECTEDDB/{$arg['table']}";

        if (($rows = $this->_readFile($filename . '.MYD')) === false ||
             ($cols = $this->_readFile($filename . '.FRM')) === false) {
            return $this->_error(E_USER_NOTICE, 'Table \'' . $arg['table'] . '\' doesn\'t exist');
        }

        if (empty($rows)) {
            return array();
        }

        /* Parse the limit clause, looking for any complications, like finish
         * value larger than the start value, non-numeric values, if no
         * limit is specified, or is it is not an array. */
        switch (true) {
            /* No boundaries given or it's not an array  */
            case (empty($arg['limit']) || !is_array($arg['limit'])):
            {
                $arg['limit']['0'] = 0;
                $arg['limit']['1'] = count($rows);

                break;
            }

            /* The first boundary is given, the second isn't */
            case (!empty($arg['limit'][0]) && !isset($arg['limit'][1])):
            {
                $arg['limit'][1] = $arg['limit'][0];
                $arg['limit'][0] = 0;

                break;
            }

            /* The first boundary is given, second is given but it's empty */
            case (!empty($arg['limit'][0]) && isset($arg['limit'][1]) && empty($arg['limit'][1])):
            {
                $arg['limit'][1] = count($rows);

                break;
            }

            /* First boundary bigger than the second boundary */
            case ($arg['limit'][0] > $arg['limit'][1]):
            {
                $arg['limit'][1] = $arg['limit'][0] + $arg['limit'][1];
                break;
            }
        }

        $arg['limit'][0] = ( int ) $arg['limit'][0];
        $arg['limit'][1] = ( int ) $arg['limit'][1];

        /* Remove the quotes from an alias using the WordParser */
        if (isset($arg['aliases'])) {
            foreach ($arg['aliases'] as $key => $value) {
                $parser               = new WordParser(str_replace('\\', '\\\\', $value));
                $arg['aliases'][$key] = $parser->getNextWord(false);

                unset($parser);
            }
        }

        /* Create the selection index, this speeds things up tremendously
           because it saves calls to _getColPos() */
        $temp = array();
        foreach ($arg['select'] as $key => $value) {
            if (!$this->_isTextString($value)) {
                /* Wildcard selection; selects all the columns */
                if ($value == '*' && empty($wildCard)) {
                    $col = $cols;
                    unset($col['primary']);
                    $col = array_keys($col);

                    foreach ($col as $key1 => $value1) {
                        if (!isset($arg['aliases'][$value1])) {
                            $temp[$value1] = $this->_getColPos($value1, $cols);
                        }
                    }
                }

                /* Select a specific column */
                else {
                    /* This column uses a function */
                    if ((substr_count($value, '(') > 0) && (substr_count($value, '(') == substr_count($value, ')'))) {
                        $arg['select'][$value] = $value;
                    } else {
                        if (strtolower($value) == 'primary') {
                            if (empty($cols['primary'])) {
                                return $this->_error(E_USER_NOTICE, 'No primary key assigned to table \'' . $arg['table'] . '\'');
                            }

                            $value     = $cols['primary'];
                        }

                        if (($colPos = $this->_getColPos($value, $cols)) === false) {
                            return $this->_error(E_USER_NOTICE, 'Column \'' . $value . '\' doesn\'t exist');
                        }

                        /* Look for an alias */
                        $value                 = isset($arg['aliases'][$value]) ? $arg['aliases'][$value] : $value;
                        $arg['select'][$value] = $colPos;
                    }
                }
            } else {
                /* This is not a column, it is a string literal */
                $value1                = isset($arg['aliases'][$value]) ? $arg['aliases'][$value] : $value;
                $arg['select'][$value] = array(str_replace('\\', '\\\\', $value1));
            }

            unset($arg['select'][$key]);
        }

        $arg['select'] += $temp;

        /* Check to see if we have a where clause to work with */
        $matches = 'TRUE';

        if (isset($arg['where'])) {
            /* Create the rule to match records, this goes inside the $rowmatches()
             * function statement and tells us whether the current row matches the
             * given criteria or not */
            if (($matches = $this->_buildIf($arg['where'], $cols)) === false) {
                return false;
            }
        }

        /* Initialize Some Variables */
        $found      = -1;
        $added      = -1;
        $selected   = array();

        /* Go through each record, if the row matches and we are in our limits
         * then select the row with the proper type (string, boolean, or integer) */
        $function  = '
				foreach ( $rows as $key => $value )
				{
					if ( '.$matches.' )
					{
						$found++;
						if ( $found >= $arg[\'limit\'][0] && $found <= $arg[\'limit\'][1] )
						{
							$added++;
							';

        foreach ($arg['select'] as $key => $value) {
            /* Selecting a column */
            if (!is_array($value)) {
                /* This function uses a function */
                if (substr_count($value, '(') > 0 && substr_count($value, '(') == substr_count($value, ')')) {
                    $key         = isset($arg['aliases'][$key]) ? "'{$arg['aliases'][$key]}'" : "";
                    if (($code = $this->generateFunctionClause($value, $cols)) === false) {
                        return false;
                    }

                    $function .= "\$selected[\$added][$key] = $code;\n\t\t\t\t\t\t\t";
                } else {
                    $key       = str_replace("'", "\\'", $key);
                    $function .= "\$selected[\$added]['$key'] = \$value[$value];\n\t\t\t\t\t\t\t";
                }
            }

            /* Selecting a string literal */
            else {
                $parser    = new WordParser($value[0]);
                $value[0]  = str_replace("'", "\\'", $parser->getNextWord(false));

                $parser->setString($key);
                $key       = $parser->getNextWord(false);

                $key       = str_replace("'", "\\'", $key);
                $function .= "\$selected[\$added]['{$value[0]}'] = '$key';\n\t\t\t\t\t\t\t";
            }
        }

        $function .= '
							if ( $found >= $arg[\'limit\'][1] )
							{
								break;
							}
						}
					}
				}  ';
        @eval($function);

        /* Sort the results by a key, this is a very expensive
         * operation and can take quite some time which is why
         * it is not reccomended for large amounts of data */
        if (!empty($arg['orderby']) && !empty($selected) && count($selected) > 0) {
            $function = '
			function sort_m ( $a, $b )
			{
				$vals = array(';

            foreach ($arg['orderby'] as $key => $value) {
                if (!array_key_exists($key, $selected[0])) {
                    return $this->_error(E_USER_NOTICE, 'Cannot sort results by column \'' . $key . '\'; Column not in result set');
                } elseif ((strtolower($value) != 'asc') && (strtolower($value) != 'desc')) {
                    return $this->_error(E_USER_NOTICE, 'Results can only be sorted \'asc\' (ascending) or \'desc\' (descending)');
                }

                $function .= "'$key' => '$value',";
            }

            $function .= ');

				while ( list($key, $val) = each($vals) )
				{
					if ( strtolower($val) == "desc" )
					{
						return ( $a["$key"] > $b["$key"] ) ? -1 : 1;
					}

					return ( $a["$key"] < $b["$key"] ) ? -1 : 1;
				}
			}';
            @eval($function);

            usort($selected, 'sort_m');
        }

        /* Apply the DISTINCT feature to the result set */
        if (!empty($arg['distinct'])) {
            if ($this->_getColPos($arg['distinct'], $cols) === false) {
                return $this->_error(E_USER_NOTICE, 'Column \'' . $arg['distinct'] . '\' doesn\'t exist');
            }

            $selected = $this->_uniqueArray($selected, $arg['distinct']);
        }

        /* Save changes in the cache */
        $this->_CACHE[ $filename . '.MYD' ] = $rows;
        $this->_CACHE[ $filename . '.FRM' ] = $cols;

        /* Return the selected records */
        return $selected;
    }

    /**
     * To insert a row of data into a table.
     * @param mixed arg The arguments that are passed to the txtSQL as an array.
     * @return void
     * @access private
     */
    public function insert($arg)
    {
        /* If the user specifies a different database, then
         * automatically select it for them */
        if (!empty($arg['db'])) {
            if (!$this->selectDb($arg['db'])) {
                return false;
            }
        }

        /* If we have no database selected, or no table to work with
         * then stop script execution */
        if (empty($this->_SELECTEDDB)) {
            return $this->_error(E_USER_NOTICE, 'No database selected');
        } elseif (empty($arg['table'])) {
            return $this->_error(E_USER_NOTICE, 'No table specified');
        } elseif (!isset($arg['values']) || count($arg['values']) == 0) {
            return $this->_error(E_USER_NOTICE, 'No values given to enter into database');
        }

        /* Make sure the database isn't locked */
        if ($this->isLocked($this->_SELECTEDDB)) {
            return $this->_error(E_USER_NOTICE, 'Database \'' . $this->_SELECTEDDB . '\' is locked');
        }

        /* Check to see if the tables exist or not, if not then we cannot
         * continue, so we issue an error message */
        $filename = "$this->_LIBPATH/$this->_SELECTEDDB/{$arg['table']}";

        if (($rows = $this->_readFile($filename . '.MYD')) === false ||
             ($cols = $this->_readFile($filename . '.FRM')) === false) {
            return $this->_error(E_USER_NOTICE, 'Table \'' . $arg['table'] . '\' doesn\'t exist');
        }

        /* Create the model of the row */
        $model = array();
        foreach ($cols as $key => $value) {
            if ($key == 'primary') {
                continue;
            }

            switch (true) {
                case ($value['auto_increment'] == 1):
                {
                    $model[] = ($cols[$key]['autocount']++) + 1;

                    break;
                }

                case ($value['type'] == 'date'):
                {
                    $arg['values'][$key] = '';

                    break;
                }

                default:
                {
                    $model[] = $value['default'];
                }
            }
        }

        /* We first create the selection indexes inside the foreach loop,
         * inside the same one, we check that max values have not been
         * exceeded, the table isn't permanent, and auto increment features */
        $max = count($rows);

        foreach ($arg['values'] as $key => $value) {
            unset($arg['values'][$key]);

            /* If the user is referring to the primary column, then
             * we substitute it with the actual primary column. We
             * also check to see if the column exists or not */
            if (strtolower($key) == 'primary') {
                if (empty($cols['primary'])) {
                    return $this->_error(E_USER_NOTICE, 'No primary key assigned to table \'' . $arg['table'] . '\'');
                }
                $key = $cols['primary'];
            }

            if (($colPos = $this->_getColPos($key, $cols)) === false) {
                return $this->_error(E_USER_NOTICE, 'Column \'' . $key . '\' doesn\'t exist');
            }

            $value = array($colPos, $value);

            /* Make sure that the max value for this column has not
             * yet been exceeded */
            if ($cols[$key]['type'] == 'int' && ($cols[$key]['max'] > 0) && ($value[1] > $cols[$key]['max'])) {
                return $this->_error(E_USER_NOTICE, 'Cannot exceed maximum value for column \'' . $key . '\'');
            } elseif (($cols[$key]['max'] > 0) && (strlen($value[1]) > $cols[$key]['max'])) {
                return $this->_error(E_USER_NOTICE, 'Cannot exceed maximum value for column \'' . $key . '\'');
            }

            /* If the value is empty, and there is a default value
             * set for this column, then we substitute the value
             * with the default */
            if (empty($value[1]) && !empty($cols[$key]['default'])) {
                $value[1] = $cols[$key]['default'];
            }

            /* If this is an auto increment column, then we will
             * will use the already incremented column value */
            if ($cols[$key]['auto_increment'] == 1) {
                $value[1] = $model[$colPos];
            }

            /* Insert the new row of data into the rows of information
             * with the right data type */
            switch (strtolower($cols[$key]['type'])) {
                case 'enum':
                {
                    if (empty($cols[$key]['enum_val'])) {
                        $cols[$key]['enum_val'] = serialize(array(''));
                    }

                    $enum_val = unserialize($cols[$key]['enum_val']);

                    foreach ($enum_val as $key => $value1) {
                        if (strtolower($value[1]) == strtolower($value1)) {
                            break;
                        }

                        if ($key == (count($enum_val) - 1)) {
                            $value[1] = $enum_val[$key];

                            break;
                        }
                    }
                }

                case 'string':
                case 'text':
                {
                    $model[ $value[0] ] = ( string ) $value[1];

                    break;
                }

                case 'int':
                {
                    $model[ $value[0] ] = ( integer ) $value[1];

                    break;
                }

                case 'bool':
                {
                    $model[ $value[0] ] = ( boolean ) $value[1];

                    break;
                }

                case 'date':
                {
                    $model[ $value[0] ] = time();

                    break;
                }
            }
        }

        $rows[] = $model;

        /* Save the new information in their proper files */
        if ($this->_writeFile($filename . ".MYD", 'w', serialize($rows)) === true) {
            if ($this->_writeFile($filename . ".FRM", 'w', serialize($cols)) === true) {
                $this->_CACHE[ $filename . '.MYD' ] = $rows;
                $this->_CACHE[ $filename . '.FRM' ] = $cols;

                return true;
            }
        }

        /* Save files to cache */
        return false;
    }

    /**
     * Removes (a) row(s) that fit(s) the given credentials from a table. If none
     * are specified, it will empty out the table.
     * @param mixed arg The arguments that are passed to the txtSQL as an array.
     * @return int deleted The number of rows deleted
     * @access private
     */
    public function delete($arg)
    {
        /* If the user specifies a different database, then
         * automatically select it for them */
        if (!empty($arg['db'])) {
            if (!$this->selectDb($arg['db'])) {
                return false;
            }
        }

        /* If no database is selected, or we have no table to
         * work with, then stop execution of script */
        if (empty($this->_SELECTEDDB)) {
            return $this->_error(E_USER_NOTICE, 'No database selected');
        } elseif (empty($arg['table'])) {
            return $this->_error(E_USER_NOTICE, 'No table specified');
        }

        /* Make sure the database isn't locked */
        if ($this->isLocked($this->_SELECTEDDB)) {
            return $this->_error(E_USER_NOTICE, 'Database \'' . $this->_SELECTEDDB . '\' is locked');
        }

        /* Check to see if the tables exist or not, if not then we cannot
         * continue, so we issue an error message */
        $filename    = "$this->_LIBPATH/$this->_SELECTEDDB/{$arg['table']}";

        if (($rows = $this->_readFile($filename . '.MYD')) === false ||
             ($cols = $this->_readFile($filename . '.FRM')) === false) {
            return $this->_error(E_USER_NOTICE, 'Table \'' . $arg['table'] . '\' doesn\'t exist');
        }

        if (isset($arg['where'])) {
            if (($matches = $this->_buildIf($arg['where'], $cols)) === false) {
                return false;
            }
        }


        if (!isset($arg['limit']) || empty($arg['limit']) || !is_numeric($arg['limit'][0])) {
            $arg['limit']['0'] = count($rows);
        }

        /* Initialize some variables */
        $found   = 0;
        $deleted = 0;

        /* Go through each record, if the row matches and we are in our limits
         * then delete the row */
        $function = '
		foreach ( $rows as $key => $value )
		{
			if ( ' . (isset($matches) ? $matches : 'TRUE') . ' )
			{
				$found++;

				if ( $found <= $arg[\'limit\'][0] )
				{
					$deleted++;

					unset($rows[$key]);

					if ( $found >= $arg[\'limit\'][0] )
					{
						break;
					}

					continue;
				}

				break;
			}
		}';
        @eval($function);

        /* Save the new record information */
        if ($this->_writeFile($filename . ".MYD", 'w', serialize($rows)) === true) {
            /* Save files to cache */
            $this->_CACHE[ $filename . '.MYD' ] = $rows;
            $this->_CACHE[ $filename . '.FRM' ] = $cols;

            /* Return the number of deleted rows */
            return $deleted;
        }

        return false;
    }

    /**
     * Updates a row that matches the given credentials with
     * the new data
     * @param mixed arg The arguments that are passed to the txtSQL as an array.
     * @return int updated The number of rows that were updated
     * @access private
     */
    public function update($arg)
    {
        /* If the user specifies a different database
         * then we must automatically select it for them. */
        if (!empty($arg['db'])) {
            if (!$this->selectDb($arg['db'])) {
                return false;
            }
        }

        /* If there is no database selected, or we have no table
         * selected, then stop execution of script */
        if (empty($this->_SELECTEDDB)) {
            return $this->_error(E_USER_NOTICE, 'No database selected');
        } elseif (empty($arg['table'])) {
            return $this->_error(E_USER_NOTICE, 'No table specified');
        }

        /* Make sure the database isn't locked */
        if ($this->isLocked($this->_SELECTEDDB)) {
            return $this->_error(E_USER_NOTICE, 'Database \'' . $this->_SELECTEDDB . '\' is locked');
        }

        /* Check to see if the tables exist or not, if not then we cannot
         * continue, so we issue an error message */
        $filename    = "$this->_LIBPATH/$this->_SELECTEDDB/{$arg['table']}";

        if (($rows = $this->_readFile($filename . '.MYD')) === false ||
             ($cols = $this->_readFile($filename . '.FRM')) === false) {
            return $this->_error(E_USER_NOTICE, 'Table \'' . $arg['table'] . '\' doesn\'t exist');
        }

        /* Check to see if we have a where clause to work with */
        if (!isset($arg['where'])) {
            return $this->_error(E_USER_NOTICE, 'Must specify a where clause');
        }

        /* Create the rule to match records, this goes inside the eval()
         * statement and tells us whether the current row matches or not */
        elseif (($matches = $this->_buildIf($arg['where'], $cols)) === false) {
            return false;
        }

        /* If we have no values to substitute, issue a warning and return */
        elseif (!isset($arg['values']) || empty($arg['values'])) {
            return $this->_error(E_USER_NOTICE, 'Must specify values to update');
        }

        /* Parse the limit looking for any complications like
         * non-numeric values, and not being an array */
        if (empty($arg['limit'])) {
            $arg['limit']['0'] = count($rows);
        } elseif (!is_array($arg['limit']) || !is_numeric($arg['limit'][0]) || $arg['limit'][0] <= 0) {
            $arg['limit']['0'] = count($rows);
        }

        /* Create the selection index, this little thing saves calls
         * to _getColPos() about 10000 times, and speeds things up */
        foreach ($arg['values'] as $key => $value) {
            if (strtolower($key) == 'primary') {
                if (empty($cols['primary'])) {
                    return $this->_error(E_USER_NOTICE, 'No primary key assigned to table \'' . $arg['table'] . '\'');
                }

                $key = $cols['primary'];
            }

            /* If the column doesn't exist */
            if (($colPos = $this->_getColPos($key, $cols)) === false) {
                return $this->_error(E_USER_NOTICE, 'Column \'' . $key . '\' doesn\'t exist');
            }

            /* If the column is permanent */
            if ($cols[$key]['permanent'] == 1) {
                $this->_error(E_USER_NOTICE, 'Column \'' . $key . '\' is set to permanent');

                unset($arg['values'][$key]);

                continue;
            }

            /* does it exceed max val? */
            if (($cols[$key]['type'] == 'int') && ($cols[$key]['max'] > 0) && ($value > $cols[$key]['max'])) {
                return $this->_error(E_USER_NOTICE, 'Cannot exceed maximum value for column \'' . $key . '\'');
            } elseif (($cols[$key]['max'] > 0) && (strlen($value) > $cols[$key]['max'])) {
                return $this->_error(E_USER_NOTICE, 'Cannot exceed maximum value for column \'' . $key . '\'');
            }

            $arg['values'][$key] = array($colPos, $value);

            unset($key, $value);
        }

        /* Initialize some variables */
        $found        = 0;
        $updated      = 0;

        /* Start going through each row of information looking for a match,
         * and if it matches then updates the row with the proper information */

        $function = '	foreach ( $rows as $key => $value )
				{
					if ( ' . $matches . ' )
					{
						$found++;

						if ( $found <= $arg[\'limit\'][0] )
						{
							$updated++;';

        foreach ($arg['values'] as $key1 => $value1) {
            switch (strtolower($cols[$key1]['type'])) {
                                    case 'enum':
                                    {
                                        if (empty($cols[$key1]['enum_val'])) {
                                            $cols[$key1]['enum_val'] = 'a:0;{}';
                                        }

                                        $enum_val = unserialize($cols[$key1]['enum_val']);

                                        foreach ($enum_val as $key2 => $value2) {
                                            if (strtolower($arg['values'][$key1][1]) == strtolower($value2)) {
                                                break;
                                            }

                                            if ($key2 == (count($enum_val) - 1)) {
                                                $arg['values'][$key1][1] = $enum_val[$key2];

                                                break;
                                            }
                                        }
                                    }

                                    case 'text':
                                    case 'string':
                                    {
                                        $type = "string";

                                        break;
                                    }

                                    case 'int':
                                    {
                                        $type = "integer";

                                        break;
                                    }

                                    case 'bool':
                                    {
                                        $type = "boolean";

                                        break;
                                    }

                                    default:
                                    {
                                        $type = "string";
                                    }
                                }

            $function .= "\$rows[\$key][ $value1[0] ] = ( $type ) " . $this->_generateClause($arg['values'][$key1][1], $cols) . ";";
        }

        $function .= '				continue;
						}

						break;
					}
				}
		';
        @eval($function);

        /* Save the new row information */
        if ($this->_writeFile($filename . ".FRM", 'w', serialize($cols)) === true) {
            if ($this->_writeFile($filename . ".MYD", 'w', serialize($rows)) === true) {
                /* Save files to cache */
                $this->_CACHE[ $filename . '.MYD' ] = $rows;
                $this->_CACHE[ $filename . '.FRM' ] = $cols;

                /* Return the number of rows that were updated */
                return $updated;
            }
        }

        return false;
    }

    /**
     * Returns an array with a list of tables inside of a database
     * @param mixed arg The arguments that are passed to the txtSQL as an array.
     * @return mixed tables An array containing the tables inside of a db
     * @access private
     */
    public function showtables($arg = null)
    {
        /* Are we showing tables inside of another database? */
        if (!empty($arg['db'])) {
            /* Does it exist? */
            if (!$this->selectDb($arg['db'])) {
                return false;
            }
        }

        /* Is a database selected? */
        if (empty($this->_SELECTEDDB)) {
            return $this->_error(E_USER_NOTICE, 'No database selected');
        }

        /* Can we open the directory up? */
        if (($fp = @opendir("$this->_LIBPATH/$this->_SELECTEDDB")) === false) {
            $this->_error(E_USER_ERROR, 'Could not open directory, \'' . $this->_LIBPATH . '/' . $this->_SELECTEDDB . '\', for reading');
        }

        $table = array();

        while (($file = @readdir($fp)) !== false) {
            if (($file != ".") && ($file != "..") && ($file != 'txtsql.MYI')) {
                /* If it's a valid txtsql table */
                $extension = substr($file, strrpos($file, '.') + 1);

                if (($extension == 'MYD' || $extension == 'FRM') && is_file("$this->_LIBPATH/$this->_SELECTEDDB/$file")) {
                    $table[] = substr($file, 0, strrpos($file, '.'));
                }
            }
        }
        @closedir($fp);

        /* Get only the tables that are valid */
        $tables = array();

        foreach ($table as $key => $value) {
            if (isset($temp[$value])) {
                $tables[] = $value;
            } else {
                $temp[$value] = true;
            }
        }

        /* Return only the names of the tables */
        return !empty($tables) ? $tables : array();
    }

    /**
     * Creates a table inside of a database, with the specified credentials of the column
     * @param mixed arg The arguments that are passed to the txtSQL as an array.
     * @return void
     * @access private
     */
    public function createtable($arg = null)
    {
        /* Inside another database? */
        if (!empty($arg['db'])) {
            if (!$this->selectDb($arg['db'])) {
                return false;
            }
        }

        /* Do we have a selected database? */
        if (empty($this->_SELECTEDDB)) {
            return $this->_error(E_USER_NOTICE, 'No database selected');
        }

        /* Make sure the database isn't locked */
        if ($this->isLocked($this->_SELECTEDDB)) {
            return $this->_error(E_USER_NOTICE, 'Database \'' . $this->_SELECTEDDB . '\' is locked');
        }

        /* Do we have a valid table name? */
        if (empty($arg['table']) || !preg_match('/^[A-Za-z0-9_]+$/', $arg['table'])) {
            return $this->_error(E_USER_NOTICE, 'Table name can only contain letters, and numbers');
        }

        /* Do we have any columns? */
        if (empty($arg['columns']) || !is_array($arg['columns'])) {
            return $this->_error(E_USER_NOTICE, 'Invalid columns for table \'' . $arg['table'] . '\'');
        }

        /* Start creating an array and populating it with
         * the column names, and types */
        $cols       = array('primary' => '');
        $primaryset = false;

        foreach ($arg['columns'] as $key => $value) {
            /* What an untouched column looks like */
            $model = array('permanent'      => 0,
                       'auto_increment' => 0,
                       'max'            => 0,
                       'type'           => 'string',
                       'default'        => '',
                       'autocount'      => (int) 0,
                       'enum_val'       => '');

            /* Column cannot be named primary */
            if ($key == 'primary') {
                return $this->_error(E_USER_NOTICE, 'Use of reserved word [primary]');
            }

            /* $value has to be an array */
            if ((!empty($value) && !is_array($value)) || empty($key)) {
                return $this->_error(E_USER_NOTICE, 'Invalid columns for table \'' . $arg['table'] . '\'');
            }

            /* Go through each column type */
            foreach ($value as $key1 => $value1) {
                switch (strtolower($key1)) {
                    case 'auto_increment':
                    {
                        /* Need either a 1 or 0 */
                        $value1 = ( integer ) $value1;

                        if (($value1 != 0) && ($value1 != 1)) {
                            return $this->_error(E_USER_NOTICE, 'Auto_increment must be a boolean 1 or 0');
                        }

                        /* Has to be an integer type */
                        if (isset($value['type']) && ($value['type'] != 'int') && ($value1 == 1)) {
                            return $this->_error(E_USER_NOTICE, 'auto_increment column must be an integer type');
                        }

                        $model['auto_increment'] = $value1;

                        break;
                    }

                    case 'permanent':
                    {
                        /* Need either a 1 or 0 */
                        $value1 = ( integer ) $value1;

                        if ($value1 < 0 || $value1 > 1) {
                            return $this->_error(E_USER_NOTICE, 'Permanent must be a boolean 1 or 0');
                        }

                        $model['permanent'] = $value1;

                        break;
                    }

                    case 'max':
                    {
                        /* Need an integer value greater than -1, less than 1,000,000 */
                        $value1 = ( integer ) $value1;

                        if (($value1 < 0) || ($value1 > 1000000)) {
                            return $this->_error(E_USER_NOTICE, 'Max must be less than 1,000,000 and greater than -1');
                        }

                        $model['max'] = $value1;

                        break;
                    }

                    case 'type':
                    {
                        /* Can only accept an integer, string, boolean */
                        switch (strtolower($value1)) {
                            case 'text':
                            {
                                $model['type'] = 'text';

                                break;
                            }

                            case 'string':
                            {
                                $model['type'] = 'string';

                                break;
                            }

                            case 'int':
                            {
                                $model['type'] = 'int';

                                break;
                            }

                            case 'bool':
                            {
                                $model['type'] = 'bool';

                                break;
                            }

                            case 'enum':
                            {
                                if (!isset($value['enum_val']) || !is_array($value['enum_val']) || empty($value['enum_val'])) {
                                    return $this->_error(E_USER_NOTICE, 'Missing enum\'s list of values or invalid list inputted');
                                }

                                $model['type'] = 'enum';

                                $model['enum_val'] = serialize($value['enum_val']);

                                break;
                            }

                            case 'date':
                            {
                                $model['type'] = 'date';

                                break;
                            }

                            default:
                            {
                                return $this->_error(E_USER_NOTICE, 'Invalid column type; can only accept integers, strings, and booleans');
                            }
                        }

                        break;
                    }

                    case 'default':
                    {
                        $model['default'] = $value1;

                        break;
                    }

                    case 'primary':
                    {
                        /* Need either a 1 or 0 */
                        $value1 = ( integer ) $value1;

                        if (($value1 < 0) || ($value1 > 1)) {
                            return $this->_error(E_USER_NOTICE, 'Primary must be a boolean 1 or 0');
                        }

                        /* Make sure primary hasn't already been set */
                        if (($primaryset === true) && ($value1 == 1)) {
                            return $this->_error(E_USER_NOTICE, 'Only one primary column can be set');
                        }

                        if ($value1 == 1) {
                            /* Primary keys have to be integer and auto_increment */
                            $value['auto_increment'] = isset($value['auto_increment']) ? $value['auto_increment'] : 0;
                            $value['type']           = isset($value['type'])           ? $value['type']           : 0;

                            if (($value['auto_increment'] != 1) || ($value['type'] != 'int')) {
                                return $this->_error(E_USER_NOTICE, 'Primary keys must be of type \'integer\' and auto_increment');
                            }

                            $cols['primary'] = $key;
                        }

                        break;
                    }

                    case 'enum_val':
                    {
                        break;
                    }

                    default:
                    {
                        return $this->_error(E_USER_NOTICE, 'Invalid column definition, ["' . $key1 . '"], specified');
                    }
                }
            }

            $cols[$key] = $model;
        }

        /* Create two files, $name.myd (empty), and $name.frm (the column defintions) */
        $filename = "$this->_LIBPATH/$this->_SELECTEDDB/$arg[table]";

        /* Make sure table doesn't exist already */
        if (is_file($filename.".MYD") || is_file($filename.".FRM")) {
            return $this->_error(E_USER_NOTICE, 'Table \'' . $arg['table'] . '\' already exists');
        }

        /* Go ahead and create the files */
        if ($this->_writeFile($filename . ".FRM", 'w', serialize($cols)) === true) {
            if ($this->_writeFile($filename . ".MYD", 'w', serialize(array())) === true) {
                /* Save files to cache */
                $this->_CACHE[ $filename . '.FRM' ] = $cols;
                $this->_CACHE[ $filename . '.MYD' ] = array();

                return true;
            }
        }

        return false;
    }

    /**
     * Drops a table given that it already exists within a database
     * @param mixed arg The arguments that are passed to the txtSQL as an array.
     * @return void
     * @access private
     */
    public function droptable($arg = null)
    {
        /* Make sure that we have a name, and that it's valid */
        if (empty($arg['table']) || !preg_match('/^[A-Za-z0-9_]+$/', $arg['table'])) {
            return $this->_error(E_USER_NOTICE, 'Database name can only contain letters, and numbers');
        }

        /* Does the table exist in another database? */
        if (!empty($arg['db'])) {
            if (!$this->selectDb($arg['db'])) {
                return false;
            }
        }

        /* Do we have selected database? */
        if (empty($this->_SELECTEDDB)) {
            return $this->_error(E_USER_NOTICE, 'No database selected');
        }

        /* Make sure the database isn't locked */
        if ($this->isLocked($this->_SELECTEDDB)) {
            return $this->_error(E_USER_NOTICE, 'Database \'' . $this->_SELECTEDDB . '\' is locked');
        }

        /* Does table exist? */
        $filename = "$this->_LIBPATH/$this->_SELECTEDDB/$arg[table]";

        if (!is_file($filename . '.MYD') || !is_file($filename . '.FRM')) {
            return $this->_error(E_USER_NOTICE, 'Table ' . $arg['table'] . '\' doesn\'t exist');
        }

        /* Delete two files $name.myd, $name.frm */
        if (!@unlink($filename . '.MYD') || !@unlink($filename . '.FRM')) {
            $this->_error(E_USER_ERROR, 'Could not delete table \'' . $arg['table'] . '\'');
        }

        return true;
    }

    /**
     * Alters a table by working with its columns. You can rename, insert, edit, delete columns.
     * Also allows for manipulation of primary keys.
     * @param mixed arg The arguments that are passed to the txtSQL as an array.
     * @return void
     * @access private
     */
    public function altertable($arg = null)
    {
        /* Is inside another database? */
        if (!empty($arg['db'])) {
            if (!$this->selectDb($arg['db'])) {
                return false;
            }
        }

        /* Do we have a selected database? */
        if (empty($this->_SELECTEDDB)) {
            return $this->_error(E_USER_NOTICE, 'No database selected');
        }

        /* Make sure the database isn't locked */
        if ($this->isLocked($this->_SELECTEDDB)) {
            return $this->_error(E_USER_NOTICE, 'Database \'' . $this->_SELECTEDDB . '\' is locked');
        }

        /* Check to see if action is not empty, and name is valid */
        if (!empty($arg['name']) && !preg_match('/^[A-Za-z0-9_]+$/', $arg['name'])) {
            return $this->_error(E_USER_NOTICE, 'Names can only contain letters, numbers, and underscored');
        } elseif (empty($arg['action'])) {
            return $this->_error(E_USER_NOTICE, 'No action specified in alter table query');
        }

        /* Check to see if the table exists */
        $filename = "$this->_LIBPATH/$this->_SELECTEDDB/$arg[table]";

        if (!is_file($filename.'.MYD') || !is_file($filename.'.FRM')) {
            return $this->_error(E_USER_NOTICE, 'Table \'' . $arg['table'] . '\' doesn\'t exist');
        }

        /* Read in the information for the table */
        if (($rows = $this->_readFile($filename . '.MYD')) === false ||
             ($cols = $this->_readFile($filename . '.FRM')) === false) {
            return $this->_error(E_USER_NOTICE, 'Table \'' . $arg['table'] . '\' doesn\'t exist');
        }

        /* Check for a primary key */
        $primaryset = !empty($cols['primary']) ? true : false;

        /* Are we allowed to change the column? */
        $action = strtolower($arg['action']);

        /* Perform the proper action */
        switch (strtolower($arg['action'])) {
            /**************************************************************
             * I n s e r t   A   C o l u m n   I n t o   T h e   T a b l e
             **************************************************************/
            case 'insert':
            {
                /* Make sure we have a column name */
                if (empty($arg['name'])) {
                    return $this->_error(E_USER_NOTICE, 'Forgot to input new column\'s name');
                }

                /* Cannot name column primary */
                if ($arg['name'] == 'primary') {
                    return $this->_error(E_USER_NOTICE, 'Cannot name column primary (use of reserved words)');
                }

                /* Check whether the column exists already or not */
                elseif (isset($cols[$arg['name']])) {
                    return $this->_error(E_USER_NOTICE, 'Column \'' . $arg['name'] . '\' already exists');
                }

                /* Check to see if we have a column to insert after */
                if (empty($arg['after'])) {
                    $colNames     = array_keys($cols);
                    $arg['after'] = $colNames[ count($cols) - 1 ];
                }

                /* Parse the types for this column */
                $model = array('permanent'      => 0,
                               'auto_increment' => 0,
                               'max'            => 0,
                               'autocount'      => 0,
                               'default'        => '',
                               'enum_val'       => '',
                               'type'           => 'int');

                foreach ($arg['values'] as $key => $value) {
                    switch (strtolower($key)) {
                        case 'auto_increment':
                        {
                            /* Need either a 1 or 0 */
                            $value = ( integer ) $value;

                            if (($value < 0) || ($value > 1)) {
                                return $this->_error(E_USER_NOTICE, 'Auto_increment must be a boolean 1 or 0');
                            }

                            /* Has to be an integer type */
                            if (isset($arg['values']['type']) && ($arg['values']['type'] != 'int') && ($value == 1)) {
                                return $this->_error(E_USER_NOTICE, 'auto_increment must be an integer type');
                            }

                            $model['auto_increment'] = $value;

                            break;
                        }

                        case 'permanent':
                        {
                            /* Need either a 1 or 0 */
                            $value = ( integer ) $value;

                            if (($value < 0) || ($value > 1)) {
                                return $this->_error(E_USER_NOTICE, 'Permanent must be a boolean 1 or 0');
                            }

                            $model['permanent'] = $value;

                            break;
                        }

                        case 'max':
                        {
                            /* Need an integer value greater than -1, less than 1,000,000 */
                            $value = ( integer ) $value;

                            if (($value < 0) || ($value > 1000000)) {
                                return $this->_error(E_USER_NOTICE, 'Max must be less than 1,000,000 and greater than -1');
                            }

                            $model['max'] = $value;

                            break;
                        }

                        case 'type':
                        {
                            /* Can only accept an integer, string, boolean */
                            switch (strtolower($value)) {
                                case 'text':
                                {
                                    $model['type'] = 'text';

                                    break;
                                }

                                case 'string':
                                {
                                    $model['type'] = 'string';

                                    break;
                                }

                                case 'int':
                                {
                                    $model['type'] = 'int';

                                    break;
                                }

                                case 'bool':
                                {
                                    $model['type'] = 'bool';

                                    break;
                                }

                                case 'enum':
                                {
                                    if (!isset($arg['values']['enum_val']) || !is_array($arg['values']['enum_val']) || empty($arg['values']['enum_val'])) {
                                        return $this->_error(E_USER_NOTICE, 'Missing enum\'s list of values or invalid list inputted');
                                    }

                                    $model['type']     = 'enum';
                                    $model['enum_val'] = serialize($arg['values']['enum_val']);

                                    break;
                                }

                                case 'date':
                                {
                                    $model['type'] = 'date';

                                    break;
                                }

                                default:
                                {
                                    return $this->_error(E_USER_NOTICE, 'Invalid column type, can only accept integers, strings, and booleans');
                                }
                            }

                            break;
                        }

                        case 'default':
                        {
                            $model['default'] = $value;

                            break;
                        }

                        case 'primary':
                        {
                            /* Need either a 1 or 0 */
                            $value = ( integer ) $value;

                            if (($value < 0) || ($value > 1)) {
                                return $this->_error(E_USER_NOTICE, 'Primary must be a boolean 1 or 0');
                            }

                            /* Make sure primary hasn't already been set */
                            if (($primaryset === true) && ($value == 1)) {
                                return $this->_error(E_USER_NOTICE, 'Only one primary column can be set');
                            }

                            if ($value == 1) {
                                $cols['primary'] = $arg['name'];
                            }

                            break;
                        }

                        case 'enum_val':
                        {
                            break;
                        }

                        default:
                        {
                            return $this->_error(E_USER_NOTICE, 'Invalid column definition, ["' . $key . '"], specified');
                        }
                    }
                }

                /* Determine the column in which we insert after */
                if ($arg['after'] == 'primary') {
                    $afterColPos = 1;
                } else {
                    if (($afterColPos = $this->_getColPos($arg['after'], $cols) + 2) === false) {
                        return $this->_error(E_USER_NOTICE, 'Column \'' . $arg['after'] . '\' doesn\'t exist');
                    }
                }

                /* Add the column to the list of already existing columns,
                   but after the specified column */
                $i = 0;

                foreach ($cols as $key => $value) {
                    $temp[$key] = $value;
                    $i          = $i + 1;

                    if ($i == $afterColPos) {
                        $temp[ $arg['name'] ] = $model;
                    }
                }

                $cols = $temp;

                /* Add the column to each row of data */
                if (!empty($rows)) {
                    foreach ($rows as $key => $value) {
                        $i = 0;

                        foreach ($value as $key1 => $value1) {
                            if ($i < $afterColPos-1) {
                                $temp1[$key][$key1] = $value1;
                            }

                            if (($i == $afterColPos - 1) || ($i == count($value) - 1 && $i == $afterColPos - 2)) {
                                $temp1[$key][ ((($i == (count($value) - 1)) && ($i == ($afterColPos - 2))) ? ($key1 + 1) : $key1) ] = $model['default'];

                                $i++;
                            }

                            if ($i > $afterColPos-1) {
                                $temp1[$key][ $key1 + 1 ] = $value1;
                            }

                            $i++;
                        }
                    }

                    $rows = $temp1;
                }

                /* Save the information */
                if ($this->_writeFile($filename . ".FRM", 'w', serialize($cols)) === true) {
                    if ($this->_writeFile($filename . ".MYD", 'w', serialize($rows)) === true) {
                        /* Save files to cache */
                        $this->_CACHE[ $filename . '.MYD' ] = $rows;
                        $this->_CACHE[ $filename . '.FRM' ] = $cols;

                        return true;
                    }
                }

                return false;
            }

            /**************************************************
             *  M O D I F Y   A   T A B L E ' S   C O L U M N
             **************************************************/
            case 'modify':
            {
                /* Are we allowed to change this column? */
                if ($arg['name'] == 'primary') {
                    return $this->_error(E_USER_NOTICE, '\'primary\' is not a valid column');
                }

                /* Check whether the column exists already or not */
                elseif (!isset($cols[$arg['name']])) {
                    return $this->_error(E_USER_NOTICE, 'Column \'' . $arg['name'] . '\' doesn\'t exist');
                }

                /* Do we have any values to work with? */
                elseif (empty($arg['values'])) {
                    return $this->_error(E_USER_NOTICE, 'Empty column set given');
                }

                /* Parse the types for this column */
                $model = array('permanent'      => $cols[ $arg['name'] ]['permanent'],
                               'auto_increment' => $cols[ $arg['name'] ]['auto_increment'],
                               'max'            => $cols[ $arg['name'] ]['max'],
                               'type'           => $cols[ $arg['name'] ]['type'],
                               'default'        => $cols[ $arg['name'] ]['default'],
                               'autocount'      => $cols[ $arg['name'] ]['autocount'],
                               'enum_val'       => $cols[ $arg['name'] ]['enum_val']);

                foreach ($arg['values'] as $key => $value) {
                    switch (strtolower($key)) {
                        case 'auto_increment':
                        {
                            /* Need either a 1 or 0 */
                            $value = ( integer ) $value;

                            if (($value < 0) || ($value > 1)) {
                                return $this->_error(E_USER_NOTICE, 'Auto_increment must be a boolean 1 or 0');
                            }

                            /* Has to be an integer type */
                            if (isset($arg['values']['type']) && ($arg['values']['type'] != 'int') && ($value == 1)) {
                                return $this->_error(E_USER_NOTICE, 'auto_increment must be an integer type');
                            }

                            $model['auto_increment'] = $value;

                            break;
                        }

                        case 'permanent':
                        {
                            /* Need either a 1 or 0 */
                            $value = ( integer ) $value;

                            if (($value < 0) || ($value > 1)) {
                                return $this->_error(E_USER_NOTICE, 'Permanent must be a boolean 1 or 0');
                            }

                            $model['permanent'] = $value;

                            break;
                        }

                        case 'max':
                        {
                            /* Need an integer value greater than -1, less than 1,000,000 */
                            $value = ( integer ) $value;

                            if (($value < 0) || ($value > 1000000)) {
                                return $this->_error(E_USER_NOTICE, 'Max must be less than 1,000,000 and greater than -1');
                            }

                            $model['max'] = $value;

                            break;
                        }

                        case 'type':
                        {
                            /* Can only accept an integer, string, boolean */
                            switch (strtolower($value)) {
                                case 'text':
                                {
                                    $model['type'] = 'text';

                                    break;
                                }

                                case 'string':
                                {
                                    $model['type'] = 'string';

                                    break;
                                }

                                case 'int':
                                {
                                    $model['type'] = 'int';

                                    break;
                                }

                                case 'bool':
                                {
                                    $model['type'] = 'bool';

                                    break;
                                }

                                case 'enum':
                                {
                                    if (!isset($arg['values']['enum_val']) || !is_array($arg['values']['enum_val']) || empty($arg['values']['enum_val'])) {
                                        return $this->_error(E_USER_NOTICE, 'Missing enum\'s list of values or invalid list inputted');
                                    }

                                    $model['type']     = 'enum';
                                    $model['enum_val'] = serialize($arg['values']['enum_val']);

                                    break;
                                }

                                case 'date':
                                {
                                    $model['type'] = 'date';

                                    break;
                                }

                                default:
                                {
                                    return $this->_error(E_USER_NOTICE, 'Invalid column type, can only accept integers, strings, and booleans');
                                }
                            }

                            break;
                        }

                        case 'default':
                        {
                            $model['default'] = $value;

                            break;
                        }

                        case 'primary':
                        {
                            /* Need either a 1 or 0 */
                            $value = ( integer ) $value;

                            if (($value < 0) || ($value > 1)) {
                                return $this->_error(E_USER_NOTICE, 'Primary must be a boolean 1 or 0');
                            }

                            /* Make sure primary hasn't already been set */
                            if (($primaryset === true) && ($value == 1)) {
                                return $this->_error(E_USER_NOTICE, 'Only one primary column can be set');
                            }

                            if ($value == 1) {
                                $cols['primary'] = $arg['name'];
                            }

                            break;
                        }

                        case 'enum_val':
                        {
                            break;
                        }

                        default:
                        {
                            return $this->_error(E_USER_NOTICE, 'Invalid column definition, ["' . $key . '"], specified');
                        }
                    }
                }

                /* Check for a primary key */
                if ((($model['type'] != 'int') || ($model['auto_increment'] != 1)) && (strtolower($cols['primary']) == strtolower($arg['name']))) {
                    $cols['primary'] = '';
                    $this->_error(E_USER_NOTICE, 'The primary key has been dropped, column must be auto_increment, and integer');
                }


                /* Add the column to the list of columns */
                $cols[ $arg['name'] ] = $model;

                /* Save the results */
                if ($this->_writeFile($filename . ".FRM", 'w', serialize($cols)) === true) {
                    /* Save files to cache */
                    $this->_CACHE[ $filename . '.FRM' ] = $cols;

                    return true;
                }

                return false;
            }

            /**************************************************************
             *  D R O P   A   T A B L E ' S   C O L U M N
             **************************************************************/
            case 'drop':
            {
                /* Chcek for a valid name */
                if (empty($arg['name']) or !preg_match('/^[A-Za-z0-9_]+$/', $arg['name'])) {
                    return $this->_error(E_USER_NOTICE, 'Column name can only contain letters, numbers, and underscores');
                }

                /* Does the column exist? */
                if (!isset($cols[$arg['name']]) || ($arg['name'] == 'primary')) {
                    return $this->_error(E_USER_NOTICE, 'Column \'' . $arg['name'] . '\' doesn\'t exist');
                }

                /* Make sure dropping this column doesn't jeopordize the table */
                if (count($cols) - 2 <= 0) {
                    return $this->_error(E_USER_NOTICE, 'Cannot drop column; There has to be at-least ONE column present');
                }

                /* Get the position that the column was in */
                $i = -1;

                foreach ($cols as $key => $value) {
                    if (($key == $arg['name']) && ($i > -1)) {
                        $position = $i;

                        break;
                    }

                    $i++;
                }

                /* Drop the column from list of columns, including primary key */
                if ($cols['primary'] == $arg['name']) {
                    $cols['primary'] = '';
                }

                unset($cols[$arg['name']]);

                /* Delete the column from each of the rows of data */
                if (is_array($rows) && (count($rows) > 0)) {
                    foreach ($rows as $key => $value) {
                        unset($rows[$key][$position]);

                        $rows[$key] = array_splice($rows[$key], 0);
                    }
                }

                /* Save the results */
                if ($this->_writeFile($filename . ".FRM", 'w', serialize($cols)) === true) {
                    if ($this->_writeFile($filename . ".MYD", 'w', serialize($rows)) === true) {
                        /* Save files to cache */
                        $this->_CACHE[ $filename . '.MYD' ] = $rows;
                        $this->_CACHE[ $filename . '.FRM' ] = $cols;

                        return true;
                    }
                }

                return false;
            }

            /**************************************************
             *  R E N A M E   A   T A B L E ' S   C O L U M N
             **************************************************/
            case 'rename col':
            {
                /* Check for valid names */
                if (empty($arg['name']) || empty($arg['values']['name']) || !preg_match('/^[A-Za-z0-9_]+$/', $arg['values']['name'])) {
                    return $this->_error(E_USER_NOTICE, 'Column names can only contain letters, numbers, and underscores');
                }

                /* Check to make sure column exists */
                if (!isset($cols[$arg['name']])) {
                    return $this->_error(E_USER_NOTICE, 'Column \'' . $arg['name'] . '\' doesn\'t exist');
                }

                /* Check to see whether new column name doesn't exist */
                if (isset($cols[$arg['values']['name']]) && ($arg['values']['name'] != $arg['name'])) {
                    return $this->_error(E_USER_NOTICE, 'Column \'' . $arg['name'] . '\' already exists');
                }

                /* If it was primary key, change primary key */
                if ($cols['primary'] == $arg['name']) {
                    $cols['primary'] = $arg['values']['name'];
                }

                /* Rename column */
                $tmp  = $cols;
                $cols = array();

                foreach ($tmp as $key => $value) {
                    if ($key == $arg['name']) {
                        $key = $arg['values']['name'];
                    }

                    $cols[$key] = $value;
                }

                /* Save the results */
                if ($this->_writeFile($filename . ".FRM", 'w', serialize($cols)) === true) {
                    /* Save files to cache */
                    $this->_CACHE[ $filename . '.FRM' ] = $cols;

                    return true;
                }

                return false;
            }

            /**************************************************************
             *  R E N A M E   A   T A B L E   C O L L E C T I V E L Y
             **************************************************************/
            case 'rename table':
            {
                /* Check for valid names */
                if (!preg_match('/^[A-Za-z0-9_]+$/', $arg['name'])) {
                    return $this->_error(E_USER_NOTICE, 'Table name can only contain letters, numbers, and underscores');
                }

                /* Make sure new table doesn't exit */
                $fp1 = "$this->_LIBPATH/$this->_SELECTEDDB/{$arg['name']}";

                if (($arg['name'] != $arg['table']) && (is_file($fp1.'.FRM') || is_file($fp1.'.MYD'))) {
                    return $this->_error(E_USER_NOTICE, 'Table \'' . $arg['name'] . '\' already exists');
                }

                /* Do the renaming */
                @rename($filename . '.FRM', $fp1 . '.FRM') or $this->_error(E_USER_ERROR, 'Error renaming file \'' . $filename . '.FRM\'');
                @rename($filename . '.MYD', $fp1 . '.MYD') or $this->_error(E_USER_ERROR, 'Error renaming file \'' . $filename . '.MYD\'');

                return true;
            }

            /************************************************************
             *  A D D   A   P R I M A R Y   K E Y   T O   A   T A B L E
             ************************************************************/
            case 'addkey':
            {
                /* Check for a valid column name */
                if (empty($arg['values']['name'])) {
                    return $this->_error(E_USER_NOTICE, 'Invalid Column Name');
                }

                if ($this->_getColPos($arg['values']['name'], $cols) === false) {
                    return $this->_error(E_USER_NOTICE, 'Column \'' . $arg['values']['name'] . '\' doesn\'t exist');
                }

                /* Does the primary key already exist? */
                if (!empty($cols['primary'])) {
                    return $this->_error(E_USER_NOTICE, 'Primary key already set to \'' . $cols['primary'] . '\'');
                }

                /* Primary key must be integer, and auto_increment */
                if (($cols[$arg['values']['name']]['type'] != 'int') || ($cols[$arg['values']['name']]['auto_increment'] === false)) {
                    return $this->_error(E_USER_NOTICE, 'Primary key must be integer type, and auto increment');
                }

                /* Set the column as the primary */
                $cols['primary'] = $arg['values']['name'];

                /* Save the results */
                if ($this->_writeFile($filename . ".FRM", 'w', serialize($cols)) === true) {
                    /* Save files to cache */
                    $this->_CACHE[ $filename . '.FRM' ] = $cols;

                    return true;
                }

                return false;
            }

            /**************************************************************
             * D R O P   T H E   T A B L E ' S   P R I M A R Y   K E Y
             **************************************************************/
            case 'dropkey':
            {
                /* Does the table have a primary key? */
                if (empty($cols['primary'])) {
                    return $this->_error(E_USER_NOTICE, 'No Primary key exists for table \'' . $arg['table'] . '\'');
                }

                /* Delete the primary key */
                $cols['primary'] = '';

                /* Save the results */
                if ($this->_writeFile($filename . ".FRM", 'w', serialize($cols)) === true) {
                    /* Save files to cache */
                    $this->_CACHE[ $filename . '.FRM' ] = $cols;

                    return true;
                }

                return false;
            }

            default:
            {
                return $this->_error(E_USER_NOTICE, 'Invalid action specified for alter table query');
            }
        }

        return false;
    }

    /**
     * Returns an array containing a list of the columns, and their
     * corresponding properties
     * @param mixed arg The arguments that are passed to the txtSQL as an array.
     * @return mixed cols An array populated with details on the fields in a table
     * @access private
     */
    public function describe($arg = null)
    {
        /* Inside of another database? */
        if (!empty($arg['db'])) {
            if (!$this->selectDb($arg['db'])) {
                return false;
            }
        }

        /* Do we have a selected database? */
        if (empty($this->_SELECTEDDB)) {
            return $this->_error(E_USER_NOTICE, 'No database selected');
        }

        /* Does table exist? */
        $filename = "$this->_LIBPATH/$this->_SELECTEDDB/{$arg['table']}";

        if (! (is_file($filename . '.MYD') && is_file($filename . '.FRM'))) {
            return $this->_error(E_USER_NOTICE, 'Table \'' . $arg['table'] . '\' doesn\'t exist');
        }

        /* Read in the column definitions */
        if (($cols = $this->_readFile($filename . '.FRM')) === false) {
            $this->_error(E_USER_ERROR, 'Couldn\'t open file \'' . $filename . '.FRM\' for reading');
        }

        /* Return the information */
        $errorLevel = error_reporting(0);

        foreach ($cols as $key => $col) {
            if ($cols[$key]['type'] == 'enum') {
                $cols[$key]['enum_val'] = unserialize($cols[$key]['enum_val']);
            }
        }

        error_reporting($errorLevel);

        return $cols;
    }

    /**
     * Returns a list of all the databases in the current working directory
     * @return mixed db An array populated with the list of databases in the CWD
     * @access private
     */
    public function showdatabases()
    {
        /* Can we open the directory up? */
        if (($fp = @opendir("$this->_LIBPATH")) === false) {
            $this->_error(E_USER_ERROR, 'Could not open directory, \'' . $this->_LIBPATH . '\', for reading');
        }

        /* Make sure that it's a directory, and not a '..' or '.' */
        while (($file = @readdir($fp)) !== false) {
            if (($file != ".") && ($file != "..") && (strtolower($file) != 'txtsql') && (is_dir("$this->_LIBPATH/$file"))) {
                $db[] = $file;
            }
        }

        @closedir($fp);

        return isset($db) ? $db : array();
    }

    /**
     * Creates a database with the given name inside of the CWD
     * @param mixed arg The arguments that are passed to the txtSQL as an array.
     * @return void
     * @access private
     */
    public function createdatabase($arg = null)
    {
        /* Make sure that we have a name, and that it's valid */
        if (empty($arg['db']) || !preg_match('/^[A-Za-z0-9_]+$/', $arg['db'])) {
            return $this->_error(E_USER_NOTICE, 'Database name can only contain letters, and numbers');
        }

        /* Does the database already exist? */
        if ($this->_dbExist($arg['db'])) {
            return $this->_error(E_USER_NOTICE, 'Database \'' .$arg['db'] . '\' already exists');
        }

        /* Go ahead and create the database */
        if (!mkdir("$this->_LIBPATH/$arg[db]")) {
            return $this->_error(E_USER_NOTICE, 'Error creating database \'' . $arg['db'] . '\'');
        }

        return true;
    }

    /**
     * Drops a database given that it exists within the CWD
     * @param mixed arg The arguments that are passed to the txtSQL as an array.
     * @return void
     * @access private
     */
    public function dropdatabase($arg = null)
    {
        /* Do we have a valid name? */
        if (empty($arg['db']) || !preg_match('/^[A-Za-z0-9_]+$/', $arg['db'])) {
            return $this->_error(E_USER_NOTICE, 'Database name can only contain letters, and numbers');
        } elseif (strtolower($arg['db']) == 'txtsql') {
            return $this->_error(E_USER_NOTICE, 'Cannot delete database txtsql');
        }

        /* Does database exist? */
        if (!$this->_dbExist($arg['db'])) {
            return $this->_error(E_USER_NOTICE, 'Database \'' . $arg['db'] . '\' doesn\'t exist');
        }

        /* Make sure the database isn't locked */
        if ($this->isLocked($arg['db'])) {
            return $this->_error(E_USER_NOTICE, 'Database \'' . $arg['db'] . '\' is locked');
        }

        /* Remove any files inside of the directory */
        if (($fp = @opendir("$this->_LIBPATH/$arg[db]")) === false) {
            $this->_error(E_USER_ERROR, 'Could not delete database \'' . $arg['db'] . '\'');
        }

        while (($file = @readdir($fp)) !== false) {
            if (($file != ".") && ($file != "..")) {
                if (is_dir("$this->_LIBPATH/$arg[db]/$file") || !@unlink("$this->_LIBPATH/$arg[db]/$file")) {
                    return $this->_error(E_USER_NOTICE, 'Could not delete database \'' . $arg['db'] . '\'');
                }
            }
        }

        @closedir($fp);

        /* Go ahead and delete the database */
        if (!@rmdir("$this->_LIBPATH/$arg[db]")) {
            $this->_error(E_USER_ERROR, 'Could not delete database \'' . $arg['db'] . '\'');
        }

        return true;
    }

    /**
     * Updates a database by changing its name
     * @param mixed arg The arguments that are passed to the txtSQL as an array.
     * @return void
     * @access private
     */
    public function renamedatabase($arg = null)
    {
        /* Valid database names? */
        if (empty($arg[0]) || empty($arg[1]) || !preg_match('/^[A-Za-z0-9_]+$/', $arg[0]) || !preg_match('/^[A-Za-z0-9_]+$/', $arg[1])) {
            return $this->_error(E_USER_NOTICE, 'Database name can only contain letters, and numbers');
        } elseif (strtolower($arg[0]) == 'txtsql') {
            return $this->_error(E_USER_NOTICE, 'Cannot rename database txtsql');
        }

        /* Does the old or new database exist? */
        if (!$this->_dbExist($arg[0])) {
            return $this->_error(E_USER_NOTICE, 'Database \'' . $arg[0] . '\' doesn\'t exist');
        } elseif ($this->_dbExist($arg[1]) && (strtolower($arg[0]) != strtolower($arg[1]))) {
            return $this->_error(E_USER_NOTICE, 'Database \'' . $arg[1] . '\' already exists');
        }

        /* Make sure the database isn't locked */
        if ($this->isLocked($this->_SELECTEDDB)) {
            return $this->_error(E_USER_NOTICE, 'Database \'' . $this->_SELECTEDDB . '\' is locked');
        }

        /* Do the renaming */
        if (!@rename("$this->_LIBPATH/$arg[0]", "$this->_LIBPATH/$arg[1]")) {
            $this->_error(E_USER_ERROR, 'Could not rename database \'' . $arg[0] . '\', to \'' . $arg[1] . '\'');
        }

        return true;
    }
}
