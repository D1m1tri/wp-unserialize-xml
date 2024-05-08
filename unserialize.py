import xml.etree.ElementTree as ET
import phpserialize
import html

def flatten_dictionary(data, prefix=''):
    flattened = {}
    for key, value in data.items():
        # Decode the key if it's in byte format
        if isinstance(key, bytes):
            key = key.decode('utf-8')

        if prefix.startswith('_'):
            prefix = prefix[1:]

        if '_horarios_' in prefix and key.endswith('dias'):
            serialized_value = 'a:{}:{{'.format(len(value))
            for i, (k, v) in enumerate(value.items()):
                if isinstance(k, bytes):
                    k = k.decode('utf-8')
                serialized_value += 'i:{};s:{}:"{}";'.format(i,len(k), k)
            serialized_value += '}'
            flattened[f"{prefix}_dias"] = serialized_value
        elif isinstance(value, dict):
            flattened.update(flatten_dictionary(value, f"{prefix}_{key}"))
        elif isinstance(value, bytes):
            # Decode the value if it's in byte format
            value = value.decode('utf-8')
            if prefix != '':
                flattened[f"{prefix}_{key}"] = value
            else:
                flattened[key] = value
        else:
            if prefix != '':
                flattened[f"{prefix}_{key}"] = value
            else:
                flattened[key] = value
    return flattened

def unserialize_data(item_element):
    if item_element is not None:
        post_id_element = item_element.find('ns3:post_id', namespaces)
        if post_id_element is not None:
            post_id = post_id_element.text.strip()

            postmeta_data = {}
            for child in item_element:
                if child.tag.endswith('postmeta'):
                    meta_key_element = child.find('ns3:meta_key', namespaces)
                    meta_value_element = child.find('ns3:meta_value', namespaces)

                    if meta_key_element is not None and meta_value_element is not None:
                        meta_key = meta_key_element.text.strip()

                        if meta_key == 'locais':
                            # Check if meta_value_element.text is not None before accessing its value
                            if meta_value_element.text is not None:
                                meta_value = meta_value_element.text.strip()
                                # Check if the meta_value is serialized
                                if meta_value.startswith('a:') or meta_value.startswith('O:'):
                                    # Unserialize the data
                                    unserialized_value = phpserialize.loads(meta_value.encode('utf-8'))
                                    # Store the unserialized data directly
                                    postmeta_data[meta_key] = unserialized_value
                                else:
                                    postmeta_data[meta_key] = meta_value
                            else:
                                # If meta_value_element.text is None, set the value as an empty string
                                postmeta_data[meta_key] = ""


            postmeta_data = flatten_dictionary(postmeta_data)
            unserialized_data_per_item[post_id] = postmeta_data



tree = ET.parse('noticias-prceu.xml')
root = tree.getroot()

unserialized_data_per_item = {}

namespaces = {
    'ns3': 'http://wordpress.org/export/1.2/'
}

# Get the first <item> element
# first_item = root.find('.//item')
# unserialize_data(first_item)
for item_element in root.findall('.//item', namespaces):
    unserialize_data(item_element)

#print("Final result after processing All Items:")
#for key, value in unserialized_data_per_item.items():
#    print(f"\n\n==> {key}: {value}")

#print(f"Number of items: {len(unserialized_data_per_item)}")

# NOTE: This code does indeed takes all items, but nothing other than that

# NOTE: Some items may come with an URL as key, but the data is preserved anyways

# TODO: save data on xml file for wordpress import

# Generate Wordpress import XML file
wordpress_xml = ET.Element('wordpress')
items_element = ET.SubElement(wordpress_xml, 'items')

for key, value in unserialized_data_per_item.items():
    if any('@type' in k for k in value.keys()):
        value = {k.replace('@', ''): v for k, v in value.items()}
    item_element = ET.SubElement(items_element, 'item')
    for k, v in value.items():
        if isinstance(v, int):
            v = str(v)
        if isinstance(v, dict):
            sub_element = ET.SubElement(item_element, k)
            sub_element.text = str(v)
        else:
            if k.startswith('_'):
                k = k[1:]
            sub_element = ET.SubElement(item_element, k)
            sub_element.text = html.escape(v)

# Write XML file
ET.ElementTree(wordpress_xml).write('wordpress_import.xml', encoding='utf-8', xml_declaration=True)

#print("Wordpress import XML file created successfully!")
