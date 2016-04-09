var mongoose = require('mongoose');
var Schema = mongoose.Schema;
var gemoji = require('gemoji');

// create a schema
var linkSchema = new Schema({
  link_id: Number,
  item_id: String,
  user_id: String,
  url: String,
  title: String,
  image: String,
  description: String,
  created: String
}, { collection: 'urls' });

var removeEmoji = function (text) {
	var arr = Object.keys(gemoji.unicode);
	pattern = '(' + arr.join('|') + ')+';
	emojiRegex = new RegExp(pattern, 'g');

	return text.replace(emojiRegex, '');
}

linkSchema.methods.redirectUrl = function () {
	var track = "?utm_source=" + this.user_id + "&utm_medium=Clicks99&utm_campaign=" + this.title + "&utm_term=" + this.user_id + "&utm_content=" + this.title;
	
	var uri = removeEmoji(this.url + track);
	uri = uri.replace(/'/g, '-');
	return uri;
};

var Link = mongoose.model('Link', linkSchema);

module.exports = Link;
