"""Deterministic XML indentation, including Python 3.7/3.8 development hosts."""

def indent(element, level=0):
    if level == 0:
        # Python <3.8 sorts XML attributes; newer versions preserve insertion order.
        # Normalize explicitly so release catalogues are identical on both.
        for node in element.iter():
            attributes = sorted(node.attrib.items())
            node.attrib.clear()
            node.attrib.update(attributes)
    whitespace = "\n" + "  " * level
    if len(element):
        if not element.text or not element.text.strip():
            element.text = whitespace + "  "
        for child in element:
            indent(child, level + 1)
        if not child.tail or not child.tail.strip():
            child.tail = whitespace
    if level and (not element.tail or not element.tail.strip()):
        element.tail = whitespace
