var express = require('express');
var router = express.Router();
var Link = require('../models/link');

/* GET Capture Every Request. */
router.get(/^\/.*/, function(req, res, next) {
  var encoded = req.url.split('/')[1];
  var id = new Buffer(encoded, 'base64');
  if (isNaN(id)) {
    return next(new Error("Invalid URL"));
  }

  Link.findOne({
    link_id: Number(id)
  }, function(err, link) {
    if (err) {
      return res.send(err);
    }

    if (!link) return res.send(new Error("Invalid URL"));

    res.render('index', {
      title: 'Express',
      id: encoded,
      link: link,
      url: 'http://' + req.headers.host + '/' + encoded,
      redirectUri: link.redirectUrl()
    });
  });
});

module.exports = router;
