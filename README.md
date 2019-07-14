# email-flagger

A SIMPLE EMAIL DAEMON SCRAPPER FOR AN ERROR MESSAGE AND RESPONSE EMAIL

# Note

for concurrency of request made to server using imap and curl , we had to implement pthreads on 
php72 Zend Thread Safe environment so as to thread each request to imap and curl

which in turn speeds up the flow of the connections.


Thank you

# Author: Jeffrey Emakpor
