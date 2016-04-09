var mongoose = require('mongoose');
var Schema = mongoose.Schema;

// create a schema
var clickSchema = new Schema({
  link_id: Number,
  item_id: String,
  user_id: String,
  click: Number,
  country: String,
  created: String
}, { collection: 'clicks' });

clickSchema.statics.process = function (opts, cb) {
	return this.findOne({
		link_id: opts.link_id,
		country: opts.country,
		created: opts.created,
		user_id: opts.user_id,
		item_id: opts.item_id
	}, function (err, doc) {
		if (err) {
			return cb(err);
		}
		if (!doc) {
			return cb(null, null);
		} else {
			var click = doc.click;
			doc.click = click + 1;
		}
		return cb(null, doc);
	});
};
var Click = mongoose.model('Click', clickSchema);
module.exports = Click;
