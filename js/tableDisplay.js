function displayTable(data, rows_d){
	jQuery(document).ready(function() {
	var t = jQuery('#example1').DataTable();
			y = JSON.parse(data);
			to_disp = rows_d.split(",");
			
			for (i = 0; i<y.length;i++){
				var j = 0;
				var tbl_arr =[];
				for(;j < to_disp.length; j++){
					tbl_arr.push(y[i][to_disp[j]])
				}
				t.row.add( tbl_arr ).draw();
			} 
		
	});
}
