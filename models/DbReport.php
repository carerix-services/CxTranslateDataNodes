<?php
class DbReport extends SQLiteObject {
	/**
	 * Holder of the prepared statements. Should speed up the DB connection.
	 */
	protected static $_preparedStatements = array();
	
	/**
	 * (non-PHPdoc)
	 * @see SQLiteObject::_getDbLocation()
	 */
	protected function _getDbLocation() {
		return ROOTDIR . '/db/.reports.' . time();
	} // _getDbLocation()';
	
	/**
	 * Returns the field types for this object
	 * 
	 * @return multitype:StdClass
	 */
	public function getModel() {
		$class = __CLASS__;
		return array(
				'id' => (object) array(
						'type' => 'INTEGER',
						'constraint' => 'PRIMARY KEY AUTOINCREMENT',
						'description' => "Unique id of the {$class}",
				),
				'creation' => (object) array(
						'type' => 'TEXT',
						'default' => SQLiteObject::getNow(false),
						'realtype' => 'datetime',
						'description' => "When was the {$class} created",
				),
				'app' => (object) array(
						'type' => 'TEXT',
						'description' => "The customer's APP for this {$class}.", 
				),
				'datanodeid' => (object) array(
						'type' => 'INTEGER',
						'description' => "Unique id of the {$class}",
				),
				'success' => (object) array(
						'type' => 'INTEGER',
						'realtype' => 'BOOLEAN',
						'description' => 'the status result, if true, this ID was successfully updated'
				),
				'message' => (object) array(
						'type' => 'TEXT',
						'description' => "The message generated for this report",
						'default' => '',
				),
				'language' => (object) array(
						'type' => 'TEXT',
						'description' => "The language for which the report was generated",
						'default' => '',
				),
		);
	} // getFields();

} // end class DbReport