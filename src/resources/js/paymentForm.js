function findClosestParent(startElement, fn) {
  var parent = startElement.parentElement;
  if (!parent) {
    return undefined;
  }
  return fn(parent) ? parent : findClosestParent(parent, fn);
}

function initWorldpay() {
  // Because this might get executed before Worldpay is loaded.
  if (typeof Worldpay === "undefined") {
    setTimeout(initWorldpay, 200);
  } else {
    var $wrapper = document.querySelector('.worldpay-form');
    var $renderDiv = $wrapper.firstElementChild;
    var key = $wrapper.dataset.clientkey;
    var paymentFormNamespace = $wrapper.dataset.paymentFormNamespace;
    var tokenInputName = paymentFormNamespace + '[worldpayToken]';
    var $form = findClosestParent($wrapper, function(element) {
      return element.tagName === 'FORM';
    });

    $form.addEventListener('submit', function (ev) {
      if (!ev.currentTarget.querySelector('input[name="' + tokenInputName + '"]'))
      {
        ev.preventDefault();
        Worldpay.submitTemplateForm();
        return false;
      }
    });

    Worldpay.useTemplateForm({
      clientKey: key,
      form:'paymentForm',
      paymentSection:$renderDiv.id,
      display:'inline',
      reusable:true,
      saveButton: false,
      callback: function(obj) {
        if (obj && obj.token) {
          var _el = document.createElement('input');
          _el.value = obj.token;
          _el.type = 'hidden';
          _el.name = tokenInputName;
          $form.appendChild(_el);
          $form.submit();
        }
      }
    });

    var $modal = document.querySelector('.modal');
    if ($modal && $modal.dataset.modal) {
      $modal.dataset.modal.updateSizeAndPosition();
    }
  }
}

initWorldpay();