  /*
   All Emoncms code is released under the GNU Affero General Public License.
   See COPYRIGHT.txt and LICENSE.txt.

    ---------------------------------------------------------------------
    Emoncms - open source energy visualisation
    Part of the OpenEnergyMonitor project:
    http://openenergymonitor.org
  */

  function draw_led(circle,status)
  {
    circle.clearRect(0,0,50,50);

  var radgrad = circle.createRadialGradient(30,30,0,30,30,20);  

if (status==0) {                   // red
  radgrad.addColorStop(0, '#F75D59');  
  radgrad.addColorStop(0.9, '#C11B17');  
} else if (status==1) {            // green
  radgrad.addColorStop(0, '#A7D30C');  
  radgrad.addColorStop(0.9, '#019F62');  
} else if (status==2) {           // grey
  radgrad.addColorStop(0, '#736F6E');  
  radgrad.addColorStop(0.9, '#4A4344');  
} else if (status==3) { 		  //Blue
  radgrad.addColorStop(0, '#00C9FF');  
  radgrad.addColorStop(0.9, '#00B5E2');  
} else if (status ==4) {		  // Purple
  radgrad.addColorStop(0, '#FF5F98');  
  radgrad.addColorStop(0.9, '#FF0188');  
} else if (status==5)   {         // yellow
  radgrad.addColorStop(0, '#F4F201');  
  radgrad.addColorStop(0.9, '#E4C700');  
} else {					  // Black
  radgrad.addColorStop(0, '#000000');  
  radgrad.addColorStop(0.9, '#000000');  
}

  radgrad.addColorStop(1, 'rgba(1,159,98,0)');
  // draw shapes  
 circle.fillStyle = radgrad;  
 circle.fillRect(0,0,60,60);  


}

function draw_led_ie8(circle,status)
{


	if (status==0) {			// red
	  circle.fillStyle = "#C11B17"; 
	} else if (status==1) {			// green
	  circle.fillStyle = "#019F62";   
	} else if (status==2) {			// grey  
	  circle.fillStyle = "#4A4344";  
	} else if (status==3) {			//Blue
	  circle.fillStyle = "#00B5E2";  
	} else if (status ==4) {		// Purple
	  circle.fillStyle = "#FF0188";   
	} else if (status==5)  {		// yellow
	  circle.fillStyle = "#E4C700";     
	} else {				// Black
	  circle.fillStyle = "#000000";  
	}

	circle.beginPath();
	circle.arc(25,25,20, 0,Math.PI * 2,false);
	circle.closePath();
	circle.fill()
}
