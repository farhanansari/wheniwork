<?php

class Employee {

    public function Employee($dbh) {

		$this->dbh = $dbh;
    }

    public function login($params) {

        $email = null; if (isset($params['email'])) { $email = $params['email']; }
        $password = null; if (isset($params['password'])) { $password = $params['password']; }

        if (!$email || !$password) {
            return array('status' => 0, 'error' => 'One or more require param is missing');
        }

        $sql = "select * from employee where email = '" . $this->dbh->real_escape_string($email) . "' and password = '" . $this->dbh->real_escape_string($password) . "'";

		$rs = $this->dbh->query($sql);

        if ($rs->num_rows > 0) {

            $row = $rs->fetch_assoc();

            // Set Cookies
            $this->setCookie(array(
                'id' => $row['employee_sid'],
                'name' => $row['name'],
                'email' => $row['name']
            ));

            return (array('status' => 1, 'id' => $row));

        }

        return (array('status' => 0, 'error' => 'Unable to log in'));

    }


    public function loggedIn(&$var) {

        $this->getCookies($var);

        if (!isset($var['email']) || !isset($var['id']) ) {
            return false;
        }

        return true;

    }

    // ---------------------    
    // Get Cookie
    // ---------------------    
    public function getCookies(&$var) {
        foreach ($_COOKIE as $key => $value) {
            $var[$key] = $value;
        }
    }



    // ---------------------    
    // Set Cookie
    // ---------------------    
    public function setCookie($params) {
        foreach ($params as $key => $value) {
            setcookie($key, $value, time() + (86400 * 30), "/"); // 86400 = 1 day   
        }
    }

	public function add($params) {

		// Make sure we have we have what we need
        $name = null; if (isset($params['name'])) { $name = $params['name']; }
        $role = null; if (isset($params['role'])) { $role = $params['role']; }
        $email = null; if (isset($params['email'])) { $email = $params['email']; }
        $phone = null; if (isset($params['phone'])) { $phone = $params['phone']; }

		if (!$name || !$role || !$email) {
            return (array('status' => 0, 'error' => "One or more parameters are missing"));
		}

        $sql = 'insert into employee (name, role, email, phone) values (?,?,?,?)';
        $sth = $this->dbh->prepare($sql);
        if (!$sth) {
            return array('status' => 0, 'error' => $this->dbh->error);
        }
        if (!$sth->bind_param('ssss', $name, $role, $email, $phone)) {
            return array('status' => 0, 'error' => $sth->error);
        }
        if (!$sth->execute()) {
            return array('status' => 0, 'error' => $this->dbh->error);
        }

        return array('status' => 1, 'employee_sid' => $this->dbh->insert_id);

	}

	public function terminate() {

		// TBD
		return (array('status' => 1, 'msg' => ''));
	}

	public function get($params) {

		$sql = "select * from employee where 1 = 1 ";

        $employee_sid = null; if (isset($params['employee_sid'])) { $employee_sid= $params['employee_sid']; }

		if ($employee_sid) {
			$sql .= " AND employee_sid = " . $this->dbh->real_escape_string($employee_sid);
		}

		$rs = $this->dbh->query($sql);
	    if ($rs->num_rows > 0) {
			while ($row = $rs->fetch_assoc()) { $buffer[] = $row; }
			return (array('status' => 1, 'data' => $buffer));
		}

		return (array('status' => 1, 'data' => array()));
	}

	// Get employee schedules
    public function getShifts($params) {

		$buffer = array();

        $sql = "select e.*, s.* 
				from employee e 
					left join shift s on s.employee_sid = e.employee_sid
				where 1 = 1 ";

        $employee_sid = null; if (isset($params['employee_sid'])) { $employee_sid= $params['employee_sid']; }
        $start_time = null; if (isset($params['start_time'])) { $start_time = $params['start_time']; }
        $end_time = null; if (isset($params['end_time'])) { $end_time = $params['end_time']; }

        if ($employee_sid) {
            $sql .= " AND e.employee_sid = " . $this->dbh->real_escape_string($employee_sid);
        }

		// If start and end time is given then get everyone between this time range
		if ($start_time && $end_time) {
            $sql .= " AND 	((s.start_time between '$start_time' and '$end_time') OR
							(s.end_time between '$start_time' and '$end_time'))
					";
		}

        $rs = $this->dbh->query($sql);
        if ($rs->num_rows > 0) {
            while ($row = $rs->fetch_assoc()) { 
				$eid = $row['employee_sid'];

				if (!isset($buffer[$eid])) {
					$buffer[$eid]['profile'] = array(
						'name' => $row['name'],
						'role' => $row['role'],
						'email' => $row['email'],
						'phone' => $row['phone'],
						'created_at' => $row['created_at']
					);
					$buffer[$eid]['shifts'] = array();
				}
				if ($row['start_time']) {
					$buffer[$eid]['shifts'][] = array(
						'manager_sid' => $row['manager_sid'],
						'employee_sid' => $row['employee_sid'],
						'break' => $row['break'],
						'start_time' => $row['start_time'],
						'end_time' => $row['end_time'],
						'created_at' => $row['created_at']
					);
				}
			}
            return (array('status' => 1, 'data' => $buffer));
        }

        return (array('status' => 1, 'data' => array()));
    }

    // Set schedule 
    public function changeShift($params) {

        $buffer = array();

        $shift_sid = null; if (isset($params['shift_sid'])) { $shift_sid = $params['shift_sid']; }
        $employee_sid = null; if (isset($params['employee_sid'])) { $employee_sid = $params['employee_sid']; }

        $break = null; if (isset($params['break'])) { $break = $params['break']; }
        $start_time = null; if (isset($params['start_time'])) { $start_time = $params['start_time']; }
        $end_time = null; if (isset($params['end_time'])) { $end_time = $params['end_time']; }

        if (!$shift_sid || !$employee_sid || !$start_time || !$end_time) {
            return (array('status' => 0, 'error' => 'One or more missing parameter'));
        }

        $now_dt = new DateTime('NOW');
        $now_dt_st = $now_dt->format('Y-m-d H:i:s');

        $start_dt = new DateTime($start_time);
        $start_dt_st = $start_dt->format('Y-m-d H:i:s');

        $end_dt = new DateTime($end_time);
        $end_dt_st = $end_dt->format('Y-m-d H:i:s');

        // Check to see if end time is greater than start time 
        if ($end_dt < $start_dt) {
            return (array('status' => 0, 'error' => 'Start time/date can not be greater than end date/time'));
        }

        // Check to see if start time is less than now
        if ($start_dt < $now_dt) {
            return (array('status' => 0, 'error' => 'Start time/date can not be less than now'));
        }

        // Check to see if provided time range overlaps
        $sql = "select * from shift where employee_sid = " . $this->dbh->real_escape_string($employee_sid) . "
				and shift_sid != " . $this->dbh->real_escape_string($shift_sid) ." 
                and ((start_time between '$start_dt_st' and '$end_dt_st') or
                    (end_time between '$start_dt_st' and '$end_dt_st'))";

        $rs = $this->dbh->query($sql);
        if ($rs->num_rows > 0) {
            return (array('status' => 0, 'error' => 'Start or end tiem overlaps with existing shift time range'));
        }

        // Lets set the shift
        $sql = "update shift set break = ?, start_time = ?, end_time = ?  where shift_sid = ? ";

        print $sql . "\n";

        $sth = $this->dbh->prepare($sql);
        if (!$sth) {
            return array('status' => 0, 'error' => $this->dbh->error);
        }
        if (!$sth->bind_param('sssi', $break, $start_dt_st, $end_dt_st, $shift_sid)) {
            return array('status' => 0, 'error' => $sth->error);
        }
        if (!$sth->execute()) {
            return array('status' => 0, 'error' => $this->dbh->error);
        }

        return array('status' => 1, 'rows_effecte' => $this->dbh->affected_rows);

    }

	// Set schedule	
	public function addShift($params) {

		$buffer = array();

        $employee_sid = null; if (isset($params['employee_sid'])) { $employee_sid = $params['employee_sid']; }
        $manager_sid = null; if (isset($params['manager_sid'])) { $manager_sid = $params['manager_sid']; }
        $break = null; if (isset($params['break'])) { $break = $params['break']; }
        $start_time = null; if (isset($params['start_time'])) { $start_time = $params['start_time']; }
        $end_time = null; if (isset($params['end_time'])) { $end_time = $params['end_time']; }
	
		if (!$employee_sid || !$manager_sid || !$start_time || !$end_time) {
			return (array('status' => 0, 'error' => 'One or more missing parameter'));
		}

		$now_dt = new DateTime('NOW'); 
		$now_dt_st = $now_dt->format('Y-m-d H:i:s'); 

		$start_dt = new DateTime($start_time); 
		$start_dt_st = $start_dt->format('Y-m-d H:i:s');

		$end_dt = new DateTime($end_time); 
		$end_dt_st = $end_dt->format('Y-m-d H:i:s');

		// Check to see if end time is greater than start time 
		if ($end_dt < $start_dt) {
			return (array('status' => 0, 'error' => 'Start time/date can not be greater than end date/time'));
		}

		// Check to see if start time is less than now
		if ($start_dt < $now_dt) {
			return (array('status' => 0, 'error' => 'Start time/date can not be less than now'));
		}

		// Check to see if provided time range overlaps
		$sql = "select * from shift where employee_sid = " . $this->dbh->real_escape_string($employee_sid) . "
				and ((start_time between '$start_dt_st' and '$end_dt_st') or
					(end_time between '$start_dt_st' and '$end_dt_st'))"; 

		$rs = $this->dbh->query($sql);
	    if ($rs->num_rows > 0) {
			return (array('status' => 0, 'error' => 'Start or end tiem overlaps with existing shift time range'));
		}

		// Lets set the shift
		$sql = "insert into shift (manager_sid, employee_sid, break, start_time, end_time) values (?,?,?,?,?)";

		print $sql . "\n";

        $sth = $this->dbh->prepare($sql);
        if (!$sth) {
            return array('status' => 0, 'error' => $this->dbh->error);
        }
        if (!$sth->bind_param('iisss', $manager_sid, $employee_sid, $break, $start_dt_st, $end_dt_st)) {
            return array('status' => 0, 'error' => $sth->error);
        }
        if (!$sth->execute()) {
            return array('status' => 0, 'error' => $this->dbh->error);
        }
	
        return array('status' => 1, 'shift_sid' => $this->dbh->insert_id);

	}

}
