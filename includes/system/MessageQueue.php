<?php

require_once(__DIR__."/Messages/Message.php");

Class MessageQueue
{
	protected	$queue = null;  // The array that holds all of the messages (FIFO operations).
	
	private		$queue_size = null;
	
	function __construct()
	{
		$this->queue_size = 0;
	}
	
	function push($message_obj)
	{
		//echo "PUSHING MESSAGE\n";
		$this->queue[$this->queue_size] = $message_obj; // Append to the bottom of the array.
														// Don't use $this-queue[] since the actual size of the array
														// does not get smaller when a pop happens, just the count is
														// decremented.
		$this->queue_size++;
		//echo "Message queue(PUSHED) is now: " . $this->queue_size . "\n";							
		return (true);
	}
	
	function pop()
	{
		//echo "POPPING MESSAGE\n";
		$queue_top = $this->queue[0];  // Get the first element in the array.
		$this->shiftUp();	// Move all of the elements up by one position.
		$this->queue_size--;  // Reduce the size of the queue.
		//echo "Message queue(POPPED) is now: " . $this->queue_size . "\n";	
		return $queue_top;  // Return the top element of the queue.
	}
	
	function readElement($position)  // Return an element at a certain position but does not remove the element from the queue.
	{
		//return $this->queue[$i];
	}
	
	function reOrder($element1, $element2, $element1_new_pos, $element2_new_pos)
	{
		
	}
	
	function getSize()
	{
		return ($this->queue_size);
	}
	
	private function shiftUp()
	{
		for ($i = 0; $i < $this->queue_size; $i++)
		{
			if ($i < ($this->queue_size - 1))
			{
				$this->queue[$i] = $this->queue[($i + 1)]; // Move the next element to the previous position (move up).
				unset($this->queue[$i + 1]);
			}
			else // This is the last/only element left in the array. Unset it and be done.
			{
				unset($this->queue[$i]);
			}
		}
		return (true);
	}
}
?>