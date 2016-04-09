var express = require('express');
var router = express.Router();
var Click = require('../models/click'),
  Link = require('../models/link'),
  geoip = require('geoip-lite');

var today = function() {
  var today = new Date(),
    dd = today.getDate(),
    mm = today.getMonth() + 1, //January is 0!
    yyyy = today.getFullYear();

  if (dd < 10) dd = '0' + dd;
  if (mm < 10) mm = '0' + mm;
  today = yyyy + '-' + mm + '-' + dd;
  return today;
}

var findCountry = function (opts) {
	var req = opts.req,
		lookup,
		ip, country = "IN";

	// else process the request
  ip = req.headers['x-forwarded-for'] ||
    req.connection.remoteAddress ||
    req.socket.remoteAddress ||
    req.connection.socket.remoteAddress;

  lookup = opts.geoip.lookup(ip);
  if (lookup) {
  	country = lookup.country;
  }
  return country;
}

/* GET process page. */
router.get('/process', function(req, res, next) {
  var encoded, link_id, cname, cookie, proceed, country, opts;
  encoded = req.query.id;
  if (!encoded || !req.xhr || req.get('Clicks99Track') !== 'internalReq') next(new Error('Invalid URL'));

  // decode 'id'
  link_id = new Buffer(encoded, 'base64');
  if (isNaN(link_id)) return next(new Error('Invalid URL'));
  link_id = Number(link_id);

  // check for cookie
	cname = '__track_' + link_id;
	cookie = req.cookies[cname];
	proceed = false;

  if (!cookie) {
  	res.cookie(cname, 1);
  	proceed = true;
  } else {
  	res.cookie(cname, ++cookie);
  }

  // if cookie present then no need to process the req
  if (!proceed) {
  	return res.send({
  		success: true
  	});
  }

  country = findCountry({ req: req, geoip: geoip });

  Link.findOne({
    link_id: link_id
  }, function(err, link) {
  	if (err) {
  		return next(err);
  	}

  	if (!link) {
  		return next(new Error('Invalid URL'));
  	}

    opts = {
      link_id: link_id,
      item_id: link.item_id,
      user_id: link.user_id,
      country: country,
      created: today(),
      click: 1
    };
    Click.process(opts, function(err, click) {
      if (err) {
        return next(err);
      }

      if (!click) {
        click = new Click(opts);
      }

      // click.save();
      res.json({
      	success: true
      });
    });
  });
});

module.exports = router;
