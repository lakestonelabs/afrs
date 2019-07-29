<?php
Class Daemon
{
	private		$fork_pid;
	
	static function start()
	{
		$fork_pid = pcntl_fork();
	   
	    if ($fork_pid == -1)
	    {
	        /* fork failed */
	        echo "Daeomon fork failure!\n";
	        exit(1);
	    }elseif ($fork_pid)
	    {
	        /* close the parent so we can mimic a daemon process returning processing to calling program or process. */
	        exit(0);
	    }
	    else
	    {
	        /* child becomes our daemon process */
	       posix_setsid();
	       chdir('/');
	       umask(0);
	       return posix_getpid();
	
	    }
	}
}
?>