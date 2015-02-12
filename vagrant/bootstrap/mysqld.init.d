#!/bin/sh
#
# mysqld	This shell script takes care of starting and stopping
#		the MySQL subsystem (mysqld).
#
# chkconfig: - 64 36
# description:	MySQL database server.
# processname: mysqld
# config: /etc/my.cnf
# pidfile: /var/run/mysqld/mysqld.pid
### BEGIN INIT INFO
# Provides: mysqld
# Required-Start: $local_fs $remote_fs $network $named $syslog $time
# Required-Stop: $local_fs $remote_fs $network $named $syslog $time
# Short-Description: start and stop MySQL server
# Description: MySQL database server
### END INIT INFO

# Source function library.
. /etc/rc.d/init.d/functions

# Source networking configuration.
. /etc/sysconfig/network


exec="/usr/bin/mysqld_safe"
prog="mysqld"

# Set timeouts here so they can be overridden from /etc/sysconfig/mysqld
STARTTIMEOUT=120
STOPTIMEOUT=60

[ -e /etc/sysconfig/$prog ] && . /etc/sysconfig/$prog

lockfile=/var/lock/subsys/$prog


# extract value of a MySQL option from config files
# Usage: get_mysql_option SECTION VARNAME DEFAULT
# result is returned in $result
# We use my_print_defaults which prints all options from multiple files,
# with the more specific ones later; hence take the last match.
get_mysql_option(){
	result=`/usr/bin/my_print_defaults "$1" | sed -n "s/^--$2=//p" | tail -n 1`
	if [ -z "$result" ]; then
	    # not found, use default
	    result="$3"
	fi
}

get_mysql_option mysqld datadir "/var/lib/mysql"
datadir="$result"
get_mysql_option mysqld socket "$datadir/mysql.sock"
socketfile="$result"
get_mysql_option mysqld_safe log-error "/var/log/mysqld.log"
errlogfile="$result"
get_mysql_option mysqld_safe pid-file "/var/run/mysqld/mysqld.pid"
mypidfile="$result"

# BS 20150212 pick up log file for queries from /etc/my.cnf, 
# set /tmp/mysql-query.log as default
get_mysql_option mysqld_safe log "/tmp/mysql-query.log"
logfile="$result"


start(){
    [ -x $exec ] || exit 5
    # check to see if it's already running
    RESPONSE=`/usr/bin/mysqladmin --socket="$socketfile" --user=UNKNOWN_MYSQL_USER ping 2>&1`
    if [ $? = 0 ]; then
	# already running, do nothing
	action $"Starting $prog: " /bin/true
	ret=0
    elif echo "$RESPONSE" | grep -q "Access denied for user"
    then
	# already running, do nothing
	action $"Starting $prog: " /bin/true
	ret=0
    else
    	# prepare for start
	touch "$errlogfile"
	chown mysql:mysql "$errlogfile" 
	chmod 0640 "$errlogfile"
	
	# BS 20150212 logfile for queries
	touch "$logfile"
	chown mysql:mysql "$logfile"
	chmod 0640 "$logfile"

	[ -x /sbin/restorecon ] && /sbin/restorecon "$errlogfile"
	if [ ! -d "$datadir/mysql" ] ; then
	    # First, make sure $datadir is there with correct permissions
	    if [ ! -e "$datadir" -a ! -h "$datadir" ]
	    then
		mkdir -p "$datadir" || exit 1
	    fi
	    chown mysql:mysql "$datadir"
	    chmod 0755 "$datadir"
	    [ -x /sbin/restorecon ] && /sbin/restorecon "$datadir"
	    # Now create the database
	    action $"Initializing MySQL database: " /usr/bin/mysql_install_db --datadir="$datadir" --user=mysql
	    ret=$?
	    chown -R mysql:mysql "$datadir"
	    if [ $ret -ne 0 ] ; then
		return $ret
	    fi
	fi
	chown mysql:mysql "$datadir"
	chmod 0755 "$datadir"
	# Pass all the options determined above, to ensure consistent behavior.
	# In many cases mysqld_safe would arrive at the same conclusions anyway
	# but we need to be sure.  (An exception is that we don't force the
	# log-error setting, since this script doesn't really depend on that,
	# and some users might prefer to configure logging to syslog.)
	# Note: set --basedir to prevent probes that might trigger SELinux
	# alarms, per bug #547485

	# BS 20150212 include query log file below 
	$exec   --datadir="$datadir" --socket="$socketfile" \
		--pid-file="$mypidfile" \
		--basedir=/usr --user=mysql --log="$logfile" \
		>/dev/null 2>&1 &
	safe_pid=$!
	# Spin for a maximum of N seconds waiting for the server to come up;
	# exit the loop immediately if mysqld_safe process disappears.
	# Rather than assuming we know a valid username, accept an "access
	# denied" response as meaning the server is functioning.
	ret=0
	TIMEOUT="$STARTTIMEOUT"
	while [ $TIMEOUT -gt 0 ]; do
	    RESPONSE=`/usr/bin/mysqladmin --socket="$socketfile" --user=UNKNOWN_MYSQL_USER ping 2>&1`
	    mret=$?
	    if [ $mret -eq 0 ]; then
		break
	    fi
	    # exit codes 1, 11 (EXIT_CANNOT_CONNECT_TO_SERVICE) are expected,
	    # anything else suggests a configuration error
	    if [ $mret -ne 1 -a $mret -ne 11 ]; then
		echo "$RESPONSE"
		echo "Cannot check for MySQL Daemon startup because of mysqladmin failure."
		ret=1
		break
	    fi
	    echo "$RESPONSE" | grep -q "Access denied for user" && break
	    if ! /bin/kill -0 $safe_pid 2>/dev/null; then
		echo "MySQL Daemon failed to start."
		ret=1
		break
	    fi
	    sleep 1
	    let TIMEOUT=${TIMEOUT}-1
	done
	if [ $TIMEOUT -eq 0 ]; then
	    echo "Timeout error occurred trying to start MySQL Daemon."
	    ret=1
	fi
	if [ $ret -eq 0 ]; then
	    action $"Starting $prog: " /bin/true
	    touch $lockfile
	else
	    action $"Starting $prog: " /bin/false
	fi
    fi
    return $ret
}

stop(){
	if [ ! -f "$mypidfile" ]; then
	    # not running; per LSB standards this is "ok"
	    action $"Stopping $prog: " /bin/true
	    return 0
	fi
	MYSQLPID=`cat "$mypidfile"`
	if [ -n "$MYSQLPID" ]; then
	    /bin/kill "$MYSQLPID" >/dev/null 2>&1
	    ret=$?
	    if [ $ret -eq 0 ]; then
		TIMEOUT="$STOPTIMEOUT"
		while [ $TIMEOUT -gt 0 ]; do
		    /bin/kill -0 "$MYSQLPID" >/dev/null 2>&1 || break
		    sleep 1
		    let TIMEOUT=${TIMEOUT}-1
		done
		if [ $TIMEOUT -eq 0 ]; then
		    echo "Timeout error occurred trying to stop MySQL Daemon."
		    ret=1
		    action $"Stopping $prog: " /bin/false
		else
		    rm -f $lockfile
		    rm -f "$socketfile"
		    action $"Stopping $prog: " /bin/true
		fi
	    else
		action $"Stopping $prog: " /bin/false
	    fi
	else
	    # failed to read pidfile, probably insufficient permissions
	    action $"Stopping $prog: " /bin/false
	    ret=4
	fi
	return $ret
}
 
restart(){
    stop
    start
}

condrestart(){
    [ -e $lockfile ] && restart || :
}


# See how we were called.
case "$1" in
  start)
    start
    ;;
  stop)
    stop
    ;;
  status)
    status $prog
    ;;
  restart)
    restart
    ;;
  condrestart|try-restart)
    condrestart
    ;;
  reload)
    exit 3
    ;;
  force-reload)
    restart
    ;;
  *)
    echo $"Usage: $0 {start|stop|status|restart|condrestart|try-restart|reload|force-reload}"
    exit 2
esac

exit $?
