define(['jquery', 'core/modal_factory', 'core/templates'],
	function($, ModalFactory, Templates)
	{
		var trigger = $('#button');
		ModalFactory.create({
			title: "<h2 style='color:rgb(0, 203, 220);"+
					"text-align: center'>Try your HTML code</h2>",
			large: true,
			body: Templates.render('block_angela/modal', {}),
		}, trigger)
		.done(function(modal) {
		});
	});