<?php

# Author: Farhan Ansari
# Description
# Methods
# Methods
#
#-----------------------------------------------------------
require_once 'API.php';
require_once 'Employee.php';

class WIW extends API {

    public function WIW($request, $origin, $dbh) {

		$this->emp = new Employee($dbh);
        parent::__construct($request);
            
    }

	public function getShifts() {

		return $this->emp->getShifts( array( 'employee_sid' => $_GET['id']));

	}

	// More wrapper methods here ....
}
